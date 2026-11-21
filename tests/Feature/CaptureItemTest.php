<?php

use App\Enums\ItemStatus;
use App\Jobs\ProcessItem;
use App\Models\Item;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['pkm.capture_token' => 'secret-token']);
});

test('the web form stores the dump and queues processing', function () {
    $response = $this->postJson(route('items.store'), ['raw' => '  Lees https://laravel.com/docs. later  ']);

    $response->assertCreated()->assertJsonStructure(['id', 'html']);

    $item = Item::sole();
    expect($item->raw)->toBe('Lees https://laravel.com/docs. later')
        ->and($item->url)->toBe('https://laravel.com/docs')
        ->and($item->status)->toBe(ItemStatus::Pending);

    Queue::assertPushed(ProcessItem::class, fn (ProcessItem $job) => $job->item->is($item));
});

test('an empty dump is rejected', function () {
    $this->postJson(route('items.store'), ['raw' => ''])->assertUnprocessable();

    Queue::assertNothingPushed();
});

test('the api captures a dump with a valid token', function () {
    $this->withToken('secret-token')
        ->postJson(route('api.capture'), ['raw' => 'Idea: PKM with JEV'])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');

    expect(Item::sole()->raw)->toBe('Idea: PKM with JEV');
    Queue::assertPushed(ProcessItem::class);
});

test('the api rejects missing or wrong tokens', function (?string $token) {
    $request = $token ? $this->withToken($token) : $this;

    $request->postJson(route('api.capture'), ['raw' => 'x'])->assertUnauthorized();

    expect(Item::count())->toBe(0);
})->with([null, 'wrong-token']);

test('the api is closed when no token is configured', function () {
    config(['pkm.capture_token' => null]);

    $this->withToken('')->postJson(route('api.capture'), ['raw' => 'x'])->assertUnauthorized();
});

test('the author can say why a dump was saved, via the form and the api', function () {
    Queue::fake();

    $this->postJson(route('items.store'), ['raw' => 'https://example.com/insulation', 'why_saved' => ' For the attic, still need to read part 2 '])->assertCreated();
    $this->withToken('secret-token')->postJson(route('api.capture'), ['raw' => 'book tip', 'why_saved' => 'Tip from Anne'])->assertCreated();

    [$fromForm, $fromApi] = Item::query()->oldest('id')->get();
    expect($fromForm->why_saved)->toBe('For the attic, still need to read part 2')
        ->and($fromForm->promptText())->toContain('Why the author saved this / where they left off: For the attic, still need to read part 2')
        ->and($fromForm->embeddingText())->toContain('For the attic')
        ->and($fromApi->why_saved)->toBe('Tip from Anne')
        ->and(Item::fullTextSearch('attic')->pluck('id')->all())->toBe([$fromForm->id]);
});

test('the reason is optional', function () {
    Queue::fake();

    $this->postJson(route('items.store'), ['raw' => 'loose thought', 'why_saved' => ''])->assertCreated();

    expect(Item::sole()->why_saved)->toBeNull();
});
