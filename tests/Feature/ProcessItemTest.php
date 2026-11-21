<?php

use App\Ai\Agents\ItemAnalyzer;
use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Jobs\LinkItem;
use App\Jobs\ProcessItem;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use App\Models\Tag;
use App\Services\WebPageReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Classification;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function analysis(array $overrides = []): array
{
    return array_merge([
        'title' => 'Call the dentist',
        'summary' => 'Make a dentist appointment.',
        'body' => '- Call the dentist for a check-up',
        'tags' => ['health'],
        'actions' => [['text' => 'Call the dentist', 'due_on' => '2026-11-24']],
        'project' => '',
        'clarification_question' => '',
    ], $overrides);
}

function fakeClassification(string $type = 'todo', float $vagueProbability = 0.1): void
{
    Classification::fake([[
        'type' => new ChoiceAnswer($type, [$type => 0.9], confidence: 0.85),
        'vague' => new BooleanAnswer($vagueProbability),
    ]]);
}

test('it structures a raw dump into a ready item', function () {
    $item = Item::factory()->create(['raw' => 'call the dentist next Monday']);
    ItemAnalyzer::fake([analysis()]);
    fakeClassification('todo');
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    $item->refresh()->load(['tags', 'actions']);
    expect($item->status)->toBe(ItemStatus::Ready)
        ->and($item->type)->toBe(ItemType::Todo)
        ->and($item->title)->toBe('Call the dentist')
        ->and($item->embedding)->toHaveCount(1536)
        ->and($item->processed_at)->not->toBeNull()
        ->and($item->tags->pluck('name')->all())->toBe(['health'])
        ->and($item->actions->sole()->due_on->toDateString())->toBe('2026-11-24');

    ItemAnalyzer::assertPrompted(fn ($prompt) => $prompt->contains('call the dentist'));
    Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 1536);
});

test('it reuses existing tags and passes them to the analyzer', function () {
    Tag::factory()->create(['name' => 'laravel', 'slug' => 'laravel']);
    $item = Item::factory()->create();
    ItemAnalyzer::fake([analysis(['tags' => ['#Laravel', 'php']])]);
    fakeClassification('note');
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect(Tag::count())->toBe(2)
        ->and($item->tags()->pluck('slug')->sort()->values()->all())->toBe(['laravel', 'php']);
});

test('it asks for feedback when the dump is too vague', function () {
    $item = Item::factory()->create(['raw' => 'that thing from jan']);
    ItemAnalyzer::fake([analysis(['clarification_question' => 'Which thing from which Jan?'])]);
    fakeClassification('note', vagueProbability: 0.9);
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->refresh()->status)->toBe(ItemStatus::NeedsFeedback)
        ->and($item->clarification_question)->toBe('Which thing from which Jan?');
});

test('it does not ask again once the author answered', function () {
    $item = Item::factory()->needsFeedback()->create(['clarification_answer' => 'The drill I borrowed']);
    ItemAnalyzer::fake([analysis(['clarification_question' => 'Another question?'])]);
    fakeClassification('note', vagueProbability: 0.9);
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->refresh()->status)->toBe(ItemStatus::Ready);
    ItemAnalyzer::assertPrompted(fn ($prompt) => $prompt->contains('The drill I borrowed'));
});

test('reprocessing keeps user tags and finished actions', function () {
    $item = Item::factory()->processed()->create();
    $item->tags()->attach(Tag::factory()->create(['name' => 'manual', 'slug' => 'manual']), ['source' => 'user']);
    $item->tags()->attach(Tag::factory()->create(['name' => 'oud', 'slug' => 'oud']), ['source' => 'ai']);
    ItemAction::factory()->for($item)->done()->create(['text' => 'Already done']);
    ItemAction::factory()->for($item)->create(['text' => 'Outdated']);
    ItemAnalyzer::fake([analysis()]);
    fakeClassification();
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->tags()->pluck('slug')->sort()->values()->all())->toBe(['health', 'manual'])
        ->and($item->actions()->pluck('text')->all())->toBe(['Already done', 'Call the dentist']);
});

test('it hands the item over to the link job', function () {
    Queue::fake([LinkItem::class]);
    $item = Item::factory()->create();
    ItemAnalyzer::fake([analysis()]);
    fakeClassification();

    ProcessItem::dispatchSync($item);

    Queue::assertPushed(LinkItem::class, fn (LinkItem $job) => $job->item->is($item));
});

test('it reads the linked page once and gives it to the analyzer', function () {
    Queue::fake([LinkItem::class]);
    Http::fake([
        'example.com/*' => Http::response('<html><head><title>Insulate the attic</title></head><body><article><p>Use at least 12 cm of glass wool.</p></article></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);
    $item = Item::factory()->create(['raw' => 'read https://example.com/attic', 'url' => 'https://example.com/attic']);
    ItemAnalyzer::fake([analysis(), analysis()]);
    fakeClassification();
    fakeClassification();

    ProcessItem::dispatchSync($item);
    ProcessItem::dispatchSync($item->refresh());

    expect($item->refresh()->source_title)->toBe('Insulate the attic')
        ->and($item->source_text)->toContain('12 cm of glass wool');
    Http::assertSentCount(1);
    ItemAnalyzer::assertPrompted(fn ($prompt) => $prompt->contains('12 cm of glass wool'));
});

test('an unreadable page does not stop processing', function () {
    Queue::fake([LinkItem::class]);
    Http::fake(['example.com/*' => Http::response('Not found', 404)]);
    $item = Item::factory()->create(['raw' => 'https://example.com/weg', 'url' => 'https://example.com/weg']);
    ItemAnalyzer::fake([analysis()]);
    fakeClassification();

    ProcessItem::dispatchSync($item);

    expect($item->refresh()->status)->toBe(ItemStatus::Ready)
        ->and($item->source_text)->toBeNull()
        ->and($item->source_fetched_at)->not->toBeNull();
});

test('a failed job marks the item as failed', function () {
    $item = Item::factory()->create();

    (new ProcessItem($item))->failed(new RuntimeException('API down'));

    expect($item->refresh()->status)->toBe(ItemStatus::Failed)
        ->and($item->error)->toBe('API down');
});

test('it places the item in the active project the analyzer picks', function () {
    $project = Project::factory()->create(['name' => 'Renovate bathroom', 'goal' => 'New bathroom before summer']);
    Project::factory()->archived()->create(['name' => 'Old garden']);
    $item = Item::factory()->create(['raw' => 'look at tiles at the hardware store']);
    ItemAnalyzer::fake([analysis(['project' => 'renovate bathroom'])]);
    fakeClassification('todo');
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->projects()->pluck('projects.id')->all())->toBe([$project->id])
        ->and($item->projects->sole()->pivot->source)->toBe(AssignmentSource::Ai);
    ItemAnalyzer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'Renovate bathroom (project): New bathroom before summer')
        && ! str_contains($prompt->agent->instructions(), 'Old garden'));
});

test('it ignores project names that are not active', function () {
    Project::factory()->archived()->create(['name' => 'Old garden']);
    $item = Item::factory()->create();
    ItemAnalyzer::fake([analysis(['project' => 'Old garden'])]);
    fakeClassification('note');
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->projects()->exists())->toBeFalse();
});

test('it keeps a project the author chose by hand', function () {
    $chosen = Project::factory()->create();
    $other = Project::factory()->create();
    $item = Item::factory()->create();
    $item->projects()->attach($chosen->id, ['source' => 'user']);
    ItemAnalyzer::fake([analysis(['project' => $other->name])]);
    fakeClassification('note');
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect($item->projects()->pluck('projects.id')->all())->toBe([$chosen->id]);
});

test('a note the author wrote keeps its text, title and type and never asks for clarification', function () {
    $item = Item::factory()->create([
        'raw' => 'My text',
        'body' => 'My text',
        'title' => 'My title',
        'type' => ItemType::Idea,
        'is_authored' => true,
    ]);
    ItemAnalyzer::fake([analysis(['clarification_question' => 'What is this about?'])]);
    fakeClassification('todo', vagueProbability: 0.95);
    Queue::fake();

    (new ProcessItem($item))->handle(app(WebPageReader::class));

    $item->refresh();
    expect($item->body)->toBe('My text')
        ->and($item->title)->toBe('My title')
        ->and($item->type)->toBe(ItemType::Idea)
        ->and($item->summary)->toBe('Make a dentist appointment.')
        ->and($item->status)->toBe(ItemStatus::Ready)
        ->and($item->tags->pluck('name')->all())->toBe(['health']);
});
