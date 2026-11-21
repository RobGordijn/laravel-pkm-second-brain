<?php

use App\Models\Item;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

test('a note links to other notes by title or id and they list it as a backlink', function () {
    $byTitle = Item::factory()->processed()->create(['title' => 'Attic renovation plan']);
    $byId = Item::factory()->processed()->create(['title' => 'Contractor quote']);

    $note = Item::factory()->processed()->create(['body' => "See [[attic Renovation Plan]] and [[#{$byId->id}|the quote]]."]);

    expect($note->mentionedItems->modelKeys())->toEqualCanonicalizing([$byTitle->id, $byId->id])
        ->and($byTitle->mentionedBy->modelKeys())->toBe([$note->id]);
});

test('links that match no note, and links to the note itself, are not stored', function () {
    $note = Item::factory()->processed()->create(['title' => 'Own note', 'body' => '[[Does not exist]] [[Own note]]']);

    expect($note->mentionedItems)->toBeEmpty();
});

test('editing the body replaces the stored links', function () {
    $old = Item::factory()->processed()->create(['title' => 'Oud']);
    $new = Item::factory()->processed()->create(['title' => 'New']);
    $note = Item::factory()->processed()->create(['body' => '[[Oud]]']);

    $this->patch(route('items.update', $note), ['title' => 'Note', 'type' => 'note', 'body' => '[[New]]']);

    expect($note->mentionedItems()->pluck('items.id')->all())->toBe([$new->id])
        ->and($old->mentionedBy()->exists())->toBeFalse();
});

test('a note created later picks up links that were waiting for its title', function () {
    $note = Item::factory()->processed()->create(['body' => 'Later: [[Japan travel plan]]']);

    $target = Item::factory()->processed()->create(['title' => 'Japan travel plan']);

    expect($note->mentionedItems()->pluck('items.id')->all())->toBe([$target->id]);
});

test('renaming a note drops title links to it but keeps id links', function () {
    $target = Item::factory()->processed()->create(['title' => 'Old title']);
    $byTitle = Item::factory()->processed()->create(['body' => '[[Old title]]']);
    $byId = Item::factory()->processed()->create(['body' => "[[#{$target->id}]]"]);

    $target->update(['title' => 'New title']);

    expect($byTitle->mentionedItems()->exists())->toBeFalse()
        ->and($byId->mentionedItems()->pluck('items.id')->all())->toBe([$target->id]);
});

test('the note page renders links and backlinks', function () {
    $target = Item::factory()->processed()->create(['title' => 'Target note']);
    $note = Item::factory()->processed()->create(['title' => 'Source note', 'body' => '[[Target note]] and [[Unknown]]']);

    $this->get(route('items.show', $note))
        ->assertOk()
        ->assertSee('href="'.route('items.show', $target).'"', false)
        ->assertSee('[[Unknown]]', false)
        ->assertSeeText('Links to');

    $this->get(route('items.show', $target))->assertOk()->assertSeeText('Mentioned in')->assertSeeText('Source note');
});

test('a linked title is escaped when rendered', function () {
    $target = Item::factory()->processed()->create(['title' => '<script>alert(1)</script>']);
    $note = Item::factory()->processed()->create(['body' => "[[#{$target->id}]]"]);

    $this->get(route('items.show', $note))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('the lookup suggests matching unarchived notes except the current one', function () {
    $match = Item::factory()->processed()->create(['title' => 'Laravel queues']);
    $current = Item::factory()->processed()->create(['title' => 'Laravel tips']);
    Item::factory()->processed()->create(['title' => 'Laravel oud', 'archived_at' => now()]);
    Item::factory()->processed()->create(['title' => 'Gardening']);

    $this->getJson(route('items.lookup', ['q' => 'laravel', 'except' => $current->id]))
        ->assertOk()
        ->assertExactJson([['id' => $match->id, 'title' => 'Laravel queues']]);
});
