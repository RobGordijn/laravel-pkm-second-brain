<?php

use App\Enums\ItemStatus;
use App\Jobs\ProcessItem;
use App\Models\Item;
use Illuminate\Support\Facades\Queue;

test('writing a new note stores the text as written and queues it for tags and links', function () {
    Queue::fake();

    $response = $this->post(route('items.write.store'), ['title' => '  My  essay ', 'body' => "First line\r\nSecond line"]);

    $item = Item::sole();
    $response->assertRedirect(route('items.write.edit', $item));
    expect($item->isAuthored())->toBeTrue()
        ->and($item->title)->toBe('My essay')
        ->and($item->body)->toBe("First line\nSecond line")
        ->and($item->status)->toBe(ItemStatus::Pending);
    Queue::assertPushed(ProcessItem::class, fn (ProcessItem $job) => $job->item->is($item));
});

test('a note needs text before it can be written', function () {
    Queue::fake();

    $this->post(route('items.write.store'), ['title' => 'Only a title', 'body' => ''])->assertSessionHasErrors('body');

    expect(Item::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an autosave stores the text and delays the AI pass', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['body' => 'Oud', 'title' => 'Title']);

    $this->putJson(route('items.write.update', $item), ['title' => '', 'body' => 'New'])
        ->assertOk()
        ->assertJsonStructure(['saved_at']);

    $item->refresh();
    expect($item->body)->toBe('New')
        ->and($item->title)->toBe('Title')
        ->and($item->isAuthored())->toBeTrue();
    Queue::assertPushed(ProcessItem::class, fn (ProcessItem $job) => $job->delay !== null);
});

test('an autosave without changes does not queue the AI again', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['body' => 'Zelfde', 'title' => 'Title', 'is_authored' => true]);

    $this->putJson(route('items.write.update', $item), ['title' => 'Title', 'body' => 'Zelfde'])->assertOk();

    Queue::assertNothingPushed();
});

test('the final save returns to the note', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create();

    $this->put(route('items.write.update', $item), ['body' => 'Finished'])->assertRedirect(route('items.show', $item));
});

test('saving the editor without changing the text leaves the note to the AI', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['body' => 'Text by the AI', 'title' => 'Title']);

    $this->put(route('items.write.update', $item), ['title' => 'Title', 'body' => 'Text by the AI']);

    expect($item->refresh()->isAuthored())->toBeFalse();
    Queue::assertNothingPushed();
});

test('saving the properties panel keeps the text, title and authorship', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['body' => 'Text', 'title' => 'Title']);

    $this->patch(route('items.update', $item), ['type' => 'idea', 'tags' => 'attic', 'summary' => 'Short'])
        ->assertRedirect(route('items.show', $item));

    $item->refresh();
    expect($item->body)->toBe('Text')
        ->and($item->title)->toBe('Title')
        ->and($item->summary)->toBe('Short')
        ->and($item->isAuthored())->toBeFalse()
        ->and($item->tags->pluck('name')->all())->toBe(['attic']);
});

test('editing the body in the edit form marks the note as written by the author', function (string $body, bool $authored) {
    Queue::fake();
    $item = Item::factory()->processed()->create(['body' => "Line one\nLine two"]);

    $this->patch(route('items.update', $item), ['title' => 'Title', 'type' => 'note', 'body' => $body]);

    expect($item->refresh()->isAuthored())->toBe($authored);
})->with([
    'changed body' => ['My own text', true],
    'untouched body sent with CRLF' => ["Line one\r\nLine two", false],
]);

test('the preview renders markdown and wiki-links like the note page', function () {
    $target = Item::factory()->processed()->create(['title' => 'Goal']);

    $this->post(route('items.preview'), ['body' => '**bold** [[Goal]] <script>x</script>'])
        ->assertOk()
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('href="'.route('items.show', $target).'"', false)
        ->assertDontSee('<script>', false);
});

test('the writing pages render', function () {
    $item = Item::factory()->processed()->create(['title' => 'Existing note']);

    $this->get(route('items.write'))->assertOk()->assertSee('data-draft-key', false);
    $this->get(route('items.write.edit', $item))->assertOk()->assertSee('Existing note')->assertSee('data-autosave', false)->assertSee('Save properties');
});

test('handing a written note back to the AI rewrites it from the current text', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create(['raw' => 'Old dump', 'body' => 'My version', 'is_authored' => true]);

    $this->from(route('items.show', $item))
        ->delete(route('items.authored.destroy', $item))
        ->assertRedirect(route('items.show', $item));

    $item->refresh();
    expect($item->isAuthored())->toBeFalse()
        ->and($item->raw)->toBe('My version')
        ->and($item->status)->toBe(ItemStatus::Pending);
    Queue::assertPushed(ProcessItem::class, fn (ProcessItem $job) => $job->item->is($item));
});

test('only a written note offers to hand it back to the AI', function (bool $authored) {
    $item = Item::factory()->processed()->create(['is_authored' => $authored]);

    $response = $this->get(route('items.show', $item))->assertOk();

    $authored
        ? $response->assertSee(route('items.authored.destroy', $item), false)
        : $response->assertDontSee(route('items.authored.destroy', $item), false);
})->with(['written' => true, 'from a dump' => false]);
