<?php

use App\Ai\Agents\LinkJudge;
use App\Jobs\LinkItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

test('it embeds the item', function () {
    $item = Item::factory()->processed()->create(['title' => 'Insulate the attic']);
    Embeddings::fake();
    LinkJudge::fake()->preventStrayPrompts();

    LinkItem::dispatchSync($item);

    expect($item->refresh()->embedding)->toHaveCount(1536);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('Insulate the attic') && $prompt->dimensions === 1536);
    LinkJudge::assertNeverPrompted();
});

test('the judge decides which similar items get linked, with a reason', function () {
    $related = Item::factory()->processed()->withEmbedding(vector([1.0, 0.1]))->create(['title' => 'Order glass wool']);
    $lookalike = Item::factory()->processed()->withEmbedding(vector([1.0, 0.2]))->create(['title' => 'Call the dentist']);
    $unrelated = Item::factory()->processed()->withEmbedding(vector([0.0, 0.0, 1.0]))->create();
    $item = Item::factory()->processed()->create(['title' => 'Insulate the attic']);
    Embeddings::fake([[vector([1.0, 0.0])]]);
    LinkJudge::fake([[
        'candidates' => [
            ['id' => $related->id, 'related' => true, 'reason' => 'Both are about attic insulation.'],
            ['id' => $lookalike->id, 'related' => false, 'reason' => ''],
        ],
    ]]);

    LinkItem::dispatchSync($item);

    expect($item->relatedItems()->pluck('items.id')->all())->toBe([$related->id])
        ->and($related->relatedItems()->sole()->pivot->reason)->toBe('Both are about attic insulation.')
        ->and($lookalike->relatedItems()->count())->toBe(0)
        ->and($unrelated->relatedItems()->count())->toBe(0);

    LinkJudge::assertPrompted(fn ($prompt) => $prompt->contains('Order glass wool')
        && $prompt->contains('Call the dentist')
        && ! $prompt->contains("[id {$unrelated->id}]"));
});

test('ids the judge invents are ignored', function () {
    Item::factory()->processed()->withEmbedding(vector([1.0, 0.1]))->create();
    $item = Item::factory()->processed()->create();
    Embeddings::fake([[vector([1.0, 0.0])]]);
    LinkJudge::fake([['candidates' => [['id' => 999_999, 'related' => true, 'reason' => 'Made up']]]]);

    LinkItem::dispatchSync($item);

    expect(DB::table('item_links')->count())->toBe(0);
});

test('relinking replaces suggestions but keeps reviewed links', function () {
    $confirmed = Item::factory()->processed()->withEmbedding(vector([1.0, 0.1]))->create();
    $rejected = Item::factory()->processed()->withEmbedding(vector([1.0, 0.15]))->create();
    $stale = Item::factory()->processed()->withEmbedding(vector([0.0, 1.0]))->create();
    $item = Item::factory()->processed()->create();
    DB::table('item_links')->insert([
        ['item_id' => $item->id, 'related_item_id' => $confirmed->id, 'similarity' => 0.9, 'state' => 'confirmed'],
        ['item_id' => $item->id, 'related_item_id' => $rejected->id, 'similarity' => 0.9, 'state' => 'rejected'],
        ['item_id' => $item->id, 'related_item_id' => $stale->id, 'similarity' => 0.9, 'state' => 'suggested'],
    ]);
    Embeddings::fake([[vector([1.0, 0.0])]]);
    LinkJudge::fake()->preventStrayPrompts();

    LinkItem::dispatchSync($item);

    expect(DB::table('item_links')->where('item_id', $item->id)->pluck('state', 'related_item_id')->all())
        ->toBe([$confirmed->id => 'confirmed', $rejected->id => 'rejected']);
    LinkJudge::assertNeverPrompted();
});
