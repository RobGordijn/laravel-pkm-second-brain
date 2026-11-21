<?php

use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;

test('the explanation page shows every step of the flow', function () {
    $this->get(route('how-it-works'))
        ->assertOk()
        ->assertSeeInOrder(['Capture', 'Process', 'Organize', 'Distill', 'Express'])
        ->assertSeeInOrder(['Inbox', 'Write', 'Note', 'Classify', 'Analyze', 'Find connections', 'Review', 'Tidy tags', 'Reference', 'Archive', 'In your own words', 'Let the AI rewrite it', 'Key insight in one line', 'Ask your brain', 'Save as synthesis', 'Export'])
        ->assertSee('href="'.route('items.write').'"', false)
        ->assertSee('href="'.route('export').'"', false)
        ->assertSee('href="'.route('review.index').'"', false)
        ->assertSee('How it works');
});

test('the explanation page shows live numbers at the steps', function () {
    config(['pkm.distill_after_uses' => 3]);
    Item::factory()->processed()->count(2)->create();
    Item::factory()->needsFeedback()->create();
    Item::factory()->processed()->create(['use_count' => 4]);
    Item::factory()->processed()->create(['archived_at' => now()]);
    Project::factory()->count(2)->create();
    ItemAction::factory()->create();

    $this->get(route('how-it-works'))
        ->assertSee('1 open')
        ->assertSee('5 waiting')
        ->assertSee('2 active')
        ->assertSee('1 archived')
        ->assertSee('1 to distill');
});

test('empty counters are left out', function () {
    $this->get(route('how-it-works'))
        ->assertDontSee('waiting')
        ->assertDontSee('to distill')
        ->assertSee('0 notes');
});
