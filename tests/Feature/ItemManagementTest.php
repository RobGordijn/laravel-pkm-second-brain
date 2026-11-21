<?php

use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\LinkState;
use App\Jobs\LinkItem;
use App\Jobs\ProcessItem;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\ItemLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('the item page shows the item', function () {
    $item = Item::factory()->processed()->create(['title' => 'My note']);

    $this->get(route('items.show', $item))->assertOk()->assertSee('My note');
});

test('the item json reports processing status for polling', function () {
    $item = Item::factory()->create();

    $this->getJson(route('items.show', $item))
        ->assertOk()
        ->assertJson(['status' => 'pending', 'in_progress' => true]);
});

test('editing an item replaces its tags with user tags and relinks it', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create();

    $this->patch(route('items.update', $item), [
        'title' => 'New title',
        'summary' => 'Short',
        'body' => 'Text',
        'type' => 'idea',
        'tags' => 'project, Laravel ,',
    ])->assertRedirect(route('items.show', $item));

    $item->refresh();
    expect($item->title)->toBe('New title')
        ->and($item->tags->pluck('name')->all())->toBe(['laravel', 'project'])
        ->and($item->tags->pluck('pivot.source')->unique()->all())->toBe([AssignmentSource::User]);
    Queue::assertPushed(LinkItem::class, fn (LinkItem $job) => $job->item->is($item));
});

test('answering a clarification requeues the item', function () {
    Queue::fake();
    $item = Item::factory()->needsFeedback()->create();

    $this->post(route('items.clarification', $item), ['answer' => 'About the renovation plan'])->assertRedirect();

    expect($item->refresh()->clarification_answer)->toBe('About the renovation plan')
        ->and($item->status)->toBe(ItemStatus::Pending);
    Queue::assertPushed(ProcessItem::class);
});

test('a failed item can be retried', function () {
    Queue::fake();
    $item = Item::factory()->create(['status' => ItemStatus::Failed, 'error' => 'boom']);

    $this->post(route('items.retry', $item))->assertRedirect();

    expect($item->refresh()->status)->toBe(ItemStatus::Pending)->and($item->error)->toBeNull();
    Queue::assertPushed(ProcessItem::class);
});

test('actions can be toggled done and open', function () {
    $action = ItemAction::factory()->create();

    $this->patchJson(route('actions.update', $action))->assertJson(['done' => true]);
    $this->patchJson(route('actions.update', $action))->assertJson(['done' => false]);
});

test('rejecting a link hides it in both directions', function () {
    [$item, $related] = Item::factory()->processed()->count(2)->create();
    DB::table('item_links')->insert([
        ['item_id' => $item->id, 'related_item_id' => $related->id, 'similarity' => 0.8, 'state' => 'suggested'],
        ['item_id' => $related->id, 'related_item_id' => $item->id, 'similarity' => 0.8, 'state' => 'suggested'],
    ]);

    $this->patch(route('items.links.update', [$item, $related]), ['state' => 'rejected'])->assertRedirect();

    expect($item->relatedItems()->count())->toBe(0)->and($related->relatedItems()->count())->toBe(0);
});

test('confirming a link marks it confirmed in both directions', function () {
    [$item, $related] = Item::factory()->processed()->count(2)->create();
    ItemLink::storeBothWays($item, [$related->id => ['similarity' => 0.8, 'reason' => 'Both about the attic', 'state' => LinkState::Suggested]]);

    $this->patch(route('items.links.update', [$item, $related]), ['state' => 'confirmed'])->assertRedirect();

    expect($item->relatedItems->sole()->pivot->state)->toBe(LinkState::Confirmed)
        ->and($related->relatedItems->sole()->pivot->state)->toBe(LinkState::Confirmed);
});

test('a link cannot be put back to suggested by hand', function () {
    [$item, $related] = Item::factory()->processed()->count(2)->create();

    $this->patch(route('items.links.update', [$item, $related]), ['state' => 'suggested'])->assertSessionHasErrors('state');
});

test('the today page groups open actions by due date', function () {
    ItemAction::factory()->dueOn(today()->subDay()->toDateString())->create(['text' => 'Late']);
    ItemAction::factory()->dueOn(today()->addWeek()->toDateString())->create(['text' => 'Next week']);
    ItemAction::factory()->create(['text' => 'Someday maybe']);
    ItemAction::factory()->done()->create(['text' => 'Finished']);

    $this->get(route('today'))
        ->assertOk()
        ->assertSeeInOrder(['Late', 'Next week', 'Someday maybe'])
        ->assertDontSee('Finished');
});

test('an item can be deleted', function () {
    $item = Item::factory()->create();

    $this->delete(route('items.destroy', $item))->assertRedirect(route('items.index'));

    expect(Item::count())->toBe(0);
});

test('the item page shows the source and link reasons', function () {
    $item = Item::factory()->processed()->create([
        'url' => 'https://example.com/attic',
        'source_title' => 'Insulating the attic in 5 steps',
        'source_text' => 'Text',
        'source_fetched_at' => now(),
        'body' => "- **Glass wool** order\n- <script>alert(1)</script>",
    ]);
    $related = Item::factory()->processed()->create(['title' => 'Order glass wool']);
    DB::table('item_links')->insert(['item_id' => $item->id, 'related_item_id' => $related->id, 'similarity' => 0.8, 'reason' => 'Both are about insulation.', 'state' => 'suggested']);

    $this->get(route('items.show', $item))
        ->assertOk()
        ->assertSee('Insulating the attic in 5 steps')
        ->assertSee('<strong>Glass wool</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('Both are about insulation.');
});
