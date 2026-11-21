<?php

use App\Ai\Agents\BrainAssistant;
use App\Ai\Agents\ItemAnalyzer;
use App\Jobs\ProcessItem;
use App\Models\AiRequest;
use App\Models\Item;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Boolean;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Responses\Data\BooleanAnswer;
use Laravel\Ai\Responses\Data\ChoiceAnswer;

test('every ai call of the pipeline is logged and linked to the item', function () {
    $item = Item::factory()->create(['raw' => 'call the dentist']);
    ItemAnalyzer::fake([[
        'title' => 'Call the dentist', 'summary' => 'Call.', 'body' => 'Call', 'tags' => ['health'],
        'actions' => [], 'clarification_question' => '',
    ]]);
    Classification::fake([['type' => new ChoiceAnswer('todo', ['todo' => 1.0]), 'vague' => new BooleanAnswer(0.1)]]);
    Embeddings::fake();

    ProcessItem::dispatchSync($item);

    expect(AiRequest::orderBy('id')->pluck('name')->all())->toBe(['Classification', 'ItemAnalyzer', 'Embeddings'])
        ->and(AiRequest::pluck('item_id')->unique()->all())->toBe([$item->id]);

    $analysis = AiRequest::firstWhere('name', 'ItemAnalyzer');
    expect($analysis->kind)->toBe('agent')
        ->and($analysis->request)->toContain('call the dentist')
        ->and($analysis->response)->toContain('Call the dentist')
        ->and($analysis->duration_ms)->not->toBeNull()
        ->and($analysis->error)->toBeNull();

    expect(AiRequest::firstWhere('name', 'Classification')->request)->toContain('Questions:')
        ->and(AiRequest::firstWhere('name', 'Embeddings')->response)->toBe('1 vector(s) × 1536 dimensions');
});

test('calls outside an item job are logged without an item', function () {
    Classification::fake();

    Classification::of('Is this spam?')->question('spam', new Boolean('Is this spam?'))->classify();

    expect(AiRequest::sole()->item_id)->toBeNull();
});

test('failed calls are logged with their error', function () {
    BrainAssistant::fake(fn () => throw ProviderOverloadedException::forProvider('gemini', 503));

    try {
        (new BrainAssistant)->prompt('What do I know about insulation?');
    } catch (ProviderOverloadedException) {
    }

    $logged = AiRequest::sole();
    expect($logged->name)->toBe('BrainAssistant')
        ->and($logged->failed())->toBeTrue()
        ->and($logged->error)->toContain('overloaded')
        ->and($logged->request)->toBe('What do I know about insulation?')
        ->and($logged->response)->toBeNull();
});

test('the ai stats page shows totals and breakdowns', function () {
    AiRequest::factory()->create();
    AiRequest::factory()->failed()->create(['name' => 'LinkJudge']);

    $this->get(route('ai-log.stats'))
        ->assertOk()
        ->assertSee('(1 failed)')
        ->assertSee('Analysis')
        ->assertSee('Link judge')
        ->assertDontSee('The request');
});

test('the ai log page shows call details', function () {
    $item = Item::factory()->processed()->create(['title' => 'Insulate the attic']);
    AiRequest::factory()->for($item)->create(['request' => 'The request', 'response' => 'The response']);
    AiRequest::factory()->failed()->create(['name' => 'LinkJudge']);

    $this->get(route('ai-log.index'))
        ->assertOk()
        ->assertSee('Link judge')
        ->assertSee('Insulate the attic')
        ->assertSee('The request')
        ->assertSee('The response')
        ->assertSee('AI provider [gemini] is overloaded.');
});

test('the ai log can be filtered by purpose', function () {
    AiRequest::factory()->create(['request' => 'Analysis request']);
    AiRequest::factory()->create(['name' => 'LinkJudge', 'request' => 'Judge request']);

    $this->get(route('ai-log.index', ['name' => 'LinkJudge']))
        ->assertSee('Judge request')
        ->assertDontSee('Analysis request');
});

test('the ai log can be filtered to failed calls', function () {
    AiRequest::factory()->create(['request' => 'Successful request']);
    AiRequest::factory()->failed()->create(['request' => 'Failed request']);

    $this->get(route('ai-log.index', ['failed' => 1]))
        ->assertSee('Failed request')
        ->assertDontSee('Successful request');
});

test('old log entries are pruned', function () {
    AiRequest::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = AiRequest::factory()->create(['created_at' => now()->subDays(5)]);

    $this->artisan('model:prune', ['--model' => [AiRequest::class]])->assertSuccessful();

    expect(AiRequest::pluck('id')->all())->toBe([$recent->id]);
});

test('log names are labelled by their agent, and other names are shown as they are', function () {
    expect(AiRequest::labelFor('BrainAssistant'))->toBe('Ask your brain')
        ->and(AiRequest::labelFor('Classification'))->toBe('Classification')
        ->and(AiRequest::labelFor('../../Models/User'))->toBe('../../Models/User');
});
