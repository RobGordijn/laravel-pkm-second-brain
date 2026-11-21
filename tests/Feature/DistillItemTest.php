<?php

use App\Ai\Agents\BrainAssistant;
use App\Ai\Agents\InsightDistiller;
use App\Jobs\LinkItem;
use App\Models\Item;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(function () {
    config(['pkm.distill_after_uses' => 3]);
});

test('opening a note counts as a use without touching updated_at', function () {
    $item = Item::factory()->processed()->create(['updated_at' => now()->subWeek()]);

    $this->get(route('items.show', $item))->assertOk();
    $this->get(route('items.show', $item));

    $item->refresh();
    expect($item->use_count)->toBe(2)
        ->and($item->last_used_at)->not->toBeNull()
        ->and($item->updated_at->isBefore(now()->subDays(6)))->toBeTrue();
});

test('polling and returning after your own action are not counted as uses', function () {
    $item = Item::factory()->processed()->create();

    $this->getJson(route('items.show', $item));
    $this->withSession(['status' => 'Saved.'])->get(route('items.show', $item));

    expect($item->refresh()->use_count)->toBe(0);
});

test('notes cited in an answer count as used', function () {
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(fn () => vector([1.0, 0.0]), $prompt->inputs));
    $cited = Item::factory()->processed()->withEmbedding(vector([1.0, 0.0]))->create(['key_insight' => 'Always two quotes']);
    $notCited = Item::factory()->processed()->withEmbedding(vector([1.0, 0.01]))->create();
    BrainAssistant::fake(["Zie [#{$cited->id}]."]);

    $this->post(route('ask.store'), ['question' => 'insulation']);

    expect($cited->refresh()->use_count)->toBe(1)
        ->and($notCited->refresh()->use_count)->toBe(0);
    BrainAssistant::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Key insight (by the author): Always two quotes'));
});

test('a frequently used note without a key insight invites the author to write one', function () {
    $item = Item::factory()->processed()->create(['use_count' => 2]);

    $this->get(route('items.show', $item))
        ->assertSee('You have used this note 3×')
        ->assertSee('Let the AI suggest one');
});

test('a rarely used note does not invite yet', function () {
    $item = Item::factory()->processed()->create(['use_count' => 0]);

    $this->get(route('items.show', $item))
        ->assertDontSee('data-insight-invite', false)
        ->assertSee('Add key insight');
});

test('the AI suggestion comes back as an editable draft', function () {
    $item = Item::factory()->processed()->create(['title' => 'Insulate the attic', 'body' => 'Quote from 2 companies']);
    InsightDistiller::fake(['"Always request at least two quotes."']);

    $this->post(route('items.insight.suggest', $item))
        ->assertRedirect(route('items.show', $item))
        ->assertSessionHasInput('key_insight', 'Always request at least two quotes.');

    InsightDistiller::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Insulate the attic') && $prompt->contains('Quote from 2 companies'));
    expect($item->refresh()->key_insight)->toBeNull();

    $this->withSession(['_old_input' => ['key_insight' => 'Always request at least two quotes.']])
        ->get(route('items.show', $item))
        ->assertSee('Suggested by the AI')
        ->assertSee('value="Always request at least two quotes."', false);
});

test('an unreachable AI shows an error instead of a draft', function () {
    $item = Item::factory()->processed()->create();
    InsightDistiller::fake(fn () => throw new ProviderOverloadedException('overloaded'));

    $this->post(route('items.insight.suggest', $item))->assertSessionHasErrors('key_insight');
});

test('saving a key insight stores it, relinks the note and shows it on cards', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['use_count' => 5]);

    $this->patch(route('items.insight.update', $item), ['key_insight' => '  Always   request two quotes. '])
        ->assertRedirect(route('items.show', $item));

    expect($item->refresh()->key_insight)->toBe('Always request two quotes.')
        ->and($item->deservesKeyInsight())->toBeFalse()
        ->and($item->embeddingText())->toContain('Always request two quotes.');
    Queue::assertPushed(LinkItem::class);

    $this->get(route('items.index'))->assertSee('Always request two quotes.');
    expect(Item::fullTextSearch('quotes')->pluck('id')->all())->toBe([$item->id]);
});

test('an empty value removes the key insight', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['key_insight' => 'Oud']);

    $this->patch(route('items.insight.update', $item), ['key_insight' => ''])->assertRedirect();

    expect($item->refresh()->key_insight)->toBeNull();
});

test('the inbox lists notes worth distilling, most used first', function () {
    Item::factory()->processed()->create(['title' => 'Used often', 'use_count' => 3]);
    Item::factory()->processed()->create(['title' => 'Used very often', 'use_count' => 9]);
    Item::factory()->processed()->create(['title' => 'Already distilled', 'use_count' => 9, 'key_insight' => 'Finished']);
    Item::factory()->processed()->create(['title' => 'Rarely used', 'use_count' => 1]);

    $this->get(route('items.index', ['distill' => 1]))
        ->assertSeeInOrder(['Used very often', 'Used often'])
        ->assertDontSee('Already distilled')
        ->assertDontSee('Rarely used');
});
