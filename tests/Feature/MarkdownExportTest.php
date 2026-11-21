<?php

use App\Enums\AssignmentSource;
use App\Enums\LinkState;
use App\Models\Item;
use App\Models\ItemLink;
use App\Models\Project;
use App\Models\Tag;
use App\Services\MarkdownExport;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

test('the export page explains the export and links to the download', function () {
    Item::factory()->processed()->count(2)->create();

    $this->get(route('export'))
        ->assertOk()
        ->assertSee('2 notes')
        ->assertSee(route('export.download'));
});

test('the export downloads a zip with a markdown file per note and project', function () {
    $item = Item::factory()->processed()->create(['title' => 'My note']);
    $project = Project::factory()->create(['name' => 'Renovation']);
    $item->projects()->attach($project, ['source' => AssignmentSource::User]);

    $response = $this->get(route('export.download'));

    $response->assertOk()->assertDownload('brain-export-'.now()->toDateString().'.zip');
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());
    expect($zip->getFromName("notes/{$item->id}-my-note.md"))->toContain('# My note')
        ->and($zip->getFromName('projects/renovation.md'))->toContain("[[{$item->id}-my-note|My note]]");
});

test('a note exports with front matter, actions, confirmed links and vault links', function () {
    $target = Item::factory()->processed()->create(['title' => 'Goal']);
    $suggested = Item::factory()->processed()->create(['title' => 'Only suggested']);
    $item = Item::factory()->processed()->create([
        'title' => 'Source "with" quotes',
        'raw' => 'raw dump',
        'body' => 'See [[Goal]] and [#'.$target->id.'].',
        'url' => 'https://example.com/a',
    ]);
    $item->tags()->attach(Tag::findOrCreateByName('Travel'), ['source' => AssignmentSource::User]);
    $item->actions()->create(['text' => 'Call', 'due_on' => '2026-12-01']);
    ItemLink::storeBothWays($item, [
        $target->id => ['similarity' => 0.9, 'reason' => 'same plan', 'state' => LinkState::Confirmed],
        $suggested->id => ['similarity' => 0.8, 'reason' => null, 'state' => LinkState::Suggested],
    ]);

    $markdown = app(MarkdownExport::class)->note($item->fresh());

    expect($markdown)
        ->toStartWith("---\nid: {$item->id}\ntitle: \"Source \\\"with\\\" quotes\"\n")
        ->toContain("tags:\n  - \"travel\"")
        ->toContain('url: "https://example.com/a"')
        ->toContain("See [[{$target->id}-goal|Goal]] and [[{$target->id}-goal|#{$target->id}]].")
        ->toContain('- [ ] Call (due 2026-12-01)')
        ->toContain("- [[{$target->id}-goal|Goal]] — same plan")
        ->toContain("## Original dump\n\n> raw dump")
        ->not->toContain('Only suggested');
});
