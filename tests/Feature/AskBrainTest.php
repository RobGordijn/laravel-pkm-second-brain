<?php

use App\Ai\Agents\BrainAssistant;
use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\LinkState;
use App\Jobs\LinkItem;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn (string $input) => str_contains($input, 'insulation') ? vector([1.0, 0.05]) : vector([0.0, 0.0, 1.0]),
        $prompt->inputs,
    ));
});

test('the ask page renders', function () {
    $this->get(route('ask.index'))->assertOk()->assertSee('Ask your brain');
});

test('it answers from the most relevant notes and links the cited sources', function () {
    $attic = Item::factory()->processed()->withEmbedding(vector([1.0, 0.0]))->create(['title' => 'Insulate the attic', 'body' => 'Quote from 2 companies']);
    ItemAction::factory()->for($attic)->dueOn('2026-12-01')->create(['text' => 'Request quotes']);
    $unrelated = Item::factory()->processed()->withEmbedding(vector([0.0, 1.0]))->create(['title' => 'Call the dentist']);
    BrainAssistant::fake(["You wanted to request quotes from two companies [#{$attic->id}]. See also [#999999]."]);

    $this->post(route('ask.store'), ['question' => 'What do I know about insulation?'])
        ->assertOk()
        ->assertSee('You wanted to request quotes')
        ->assertSee('href="'.route('items.show', $attic).'"', false)
        ->assertSee('[#999999]')
        ->assertSee('Insulate the attic')
        ->assertDontSee('Call the dentist');

    BrainAssistant::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains("[#{$attic->id}] Insulate the attic")
        && $prompt->contains('Request quotes (deadline 2026-12-01)')
        && $prompt->contains('What do I know about insulation?')
        && ! $prompt->contains('Call the dentist'));
});

test('it does not call the assistant when no notes match', function () {
    BrainAssistant::fake()->preventStrayPrompts();

    $this->post(route('ask.store'), ['question' => 'Something entirely different'])
        ->assertOk()
        ->assertSee('I found no notes');

    BrainAssistant::assertNeverPrompted();
});

test('notes that are still processing are not used', function () {
    Item::factory()->withEmbedding(vector([1.0, 0.0]))->create(['status' => ItemStatus::Pending, 'raw' => 'insulation raw']);
    BrainAssistant::fake()->preventStrayPrompts();

    $this->post(route('ask.store'), ['question' => 'insulation'])->assertSee('I found no notes');
});

test('a question is required', function () {
    $this->post(route('ask.store'), ['question' => ''])->assertSessionHasErrors('question');
});

test('an unavailable ai gives a friendly message instead of an error page', function () {
    Item::factory()->processed()->withEmbedding(vector([1.0, 0.0]))->create(['title' => 'Insulate the attic']);
    BrainAssistant::fake(fn () => throw ProviderOverloadedException::forProvider('gemini', 503));

    $this->from(route('ask.index'))
        ->post(route('ask.store'), ['question' => 'insulation'])
        ->assertRedirect(route('ask.index'))
        ->assertSessionHasErrors(['question' => 'The AI is unreachable or overloaded right now. Please try again shortly.'])
        ->assertSessionHasInput('question', 'insulation');
});

test('the answer page offers to save the answer with its sources', function () {
    $attic = Item::factory()->processed()->withEmbedding(vector([1.0, 0.0]))->create();
    BrainAssistant::fake(["Request quotes [#{$attic->id}]."]);

    $this->post(route('ask.store'), ['question' => 'What do I know about insulation?'])
        ->assertSee('Save as note')
        ->assertSee('name="source_ids[]" value="'.$attic->id.'"', false);
});

test('a saved answer becomes a synthesis linked to the notes it cites', function () {
    Queue::fake();
    $cited = Item::factory()->processed()->create();
    $notCited = Item::factory()->processed()->create();
    $tag = Tag::factory()->create();
    $cited->tags()->attach($tag->id, ['source' => 'user']);
    $project = Project::factory()->create();
    $cited->projects()->attach($project->id, ['source' => 'user']);
    $archivedProject = Project::factory()->archived()->create();
    $cited->projects()->attach($archivedProject->id, ['source' => 'user']);

    $response = $this->post(route('ask.save'), [
        'question' => 'What do I know about   insulation?',
        'answer' => "**Short:** request quotes from two companies [#{$cited->id}].\n\n- Start in spring",
        'source_ids' => [$cited->id, $notCited->id],
    ]);

    $item = Item::query()->where('type', ItemType::Synthesis)->sole();
    $response->assertRedirect(route('items.show', $item));
    expect($item->title)->toBe('What do I know about insulation?')
        ->and($item->status)->toBe(ItemStatus::Ready)
        ->and($item->summary)->toBe('Short: request quotes from two companies. Start in spring')
        ->and($item->relatedItems->pluck('id')->all())->toBe([$cited->id])
        ->and($cited->relatedItems->pluck('id')->all())->toBe([$item->id])
        ->and($item->relatedItems->sole()->pivot->state)->toBe(LinkState::Confirmed)
        ->and($item->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($item->projects->pluck('id')->all())->toBe([$project->id]);
    Queue::assertPushed(LinkItem::class, fn (LinkItem $job) => $job->item->is($item));

    $this->get(route('items.show', $item))
        ->assertSee('Synthesis')
        ->assertSee('href="'.route('items.show', $cited).'"', false);
});

test('an answer without citations is linked to all notes it was given', function () {
    Queue::fake();
    [$first, $second] = Item::factory()->processed()->count(2)->create();

    $this->post(route('ask.save'), ['question' => 'Question', 'answer' => 'Answer without sources', 'source_ids' => [$first->id, $second->id, 999999]]);

    expect(Item::query()->where('type', ItemType::Synthesis)->sole()->relatedItems->pluck('id')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

test('the classifier never labels a dump as a synthesis', function () {
    expect(ItemType::classifiable())->not->toContain(ItemType::Synthesis)
        ->and(ItemType::classifiable())->toHaveCount(count(ItemType::cases()) - 1);
});
