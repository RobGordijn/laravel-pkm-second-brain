<?php

use App\Enums\AssignmentSource;
use App\Enums\ProjectKind;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

test('the projects page lists active projects and areas separately', function () {
    Project::factory()->create(['name' => 'Renovate bathroom']);
    Project::factory()->area()->create(['name' => 'Health']);
    Project::factory()->archived()->create(['name' => 'Old garden']);

    $this->get(route('projects.index'))
        ->assertOk()
        ->assertSeeInOrder(['Projects', 'Renovate bathroom', 'Areas', 'Health', 'Archive (1)', 'Old garden']);
});

test('the create forms start empty with a fixed kind', function () {
    Project::factory()->create(['name' => 'Renovate bathroom', 'goal' => 'New tiles']);

    $this->get(route('projects.index'))
        ->assertSee('New project')
        ->assertSee('New area')
        ->assertSee('<input type="hidden" name="kind" value="area">', false)
        ->assertDontSee('value="Renovate bathroom"', false)
        ->assertDontSee('>New tiles</textarea>', false);
});

test('the projects page counts unarchived notes and open actions', function () {
    $project = Project::factory()->create();
    $item = Item::factory()->processed()->create();
    $project->items()->attach([$item->id, Item::factory()->processed()->create(['archived_at' => now()])->id]);
    ItemAction::factory()->for($item)->count(2)->create();
    ItemAction::factory()->for($item)->create(['done_at' => now()]);

    $project = $this->get(route('projects.index'))->viewData('projects')->sole();

    expect($project->items_count)->toBe(1)
        ->and($project->open_actions_count)->toBe(2);
});

test('the projects page leaves out actions of notes archived on their own', function () {
    $project = Project::factory()->create();
    $archivedItem = Item::factory()->processed()->create(['archived_at' => now()]);
    $project->items()->attach($archivedItem->id);
    ItemAction::factory()->for($archivedItem)->create();

    $project = $this->get(route('projects.index'))->viewData('projects')->sole();

    expect($project->open_actions_count)->toBe(0);
});

test('an archived project counts the notes that went into the archive with it', function () {
    $project = Project::factory()->create();
    $project->items()->attach(Item::factory()->processed()->count(2)->create()->modelKeys());
    $project->archive();

    $archived = $this->get(route('projects.index'))->viewData('archived')->sole();

    expect($archived->items_count)->toBe(2);
});

test('a project can be created and gets a slug', function () {
    $this->post(route('projects.store'), [
        'name' => '  Renovate   bathroom ',
        'kind' => 'project',
        'goal' => 'Done before summer',
        'due_on' => '2027-06-01',
    ])->assertRedirect(route('projects.show', 'renovate-bathroom'));

    $project = Project::sole();
    expect($project->name)->toBe('Renovate bathroom')
        ->and($project->kind)->toBe(ProjectKind::Project)
        ->and($project->due_on->toDateString())->toBe('2027-06-01');
});

test('project names must be unique regardless of case', function () {
    Project::factory()->create(['name' => 'Health']);

    $this->post(route('projects.store'), ['name' => 'health', 'kind' => 'area'])
        ->assertSessionHasErrors('name');

    expect(Project::count())->toBe(1);
});

test('a project can be renamed', function () {
    $project = Project::factory()->create(['name' => 'Bathroom']);

    $this->patch(route('projects.update', $project), ['name' => 'Renovate bathroom', 'kind' => 'project'])
        ->assertRedirect(route('projects.show', 'renovate-bathroom'));

    expect($project->refresh()->slug)->toBe('renovate-bathroom');
});

test('the project page shows its notes and open actions', function () {
    $project = Project::factory()->create();
    $item = Item::factory()->processed()->create(['title' => 'Choose tiles']);
    $project->items()->attach($item->id, ['source' => 'user']);
    ItemAction::factory()->for($item)->create(['text' => 'Call the hardware store']);
    $other = Item::factory()->processed()->create(['title' => 'Something else']);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Choose tiles')
        ->assertSee('Call the hardware store')
        ->assertDontSee($other->title);
});

test('archiving a project archives the notes that no other active project needs', function () {
    $project = Project::factory()->create();
    $area = Project::factory()->area()->create();
    $onlyHere = Item::factory()->processed()->create();
    $shared = Item::factory()->processed()->create();
    $project->items()->attach([$onlyHere->id, $shared->id]);
    $area->items()->attach($shared->id);

    $this->post(route('projects.archive', $project))->assertRedirect(route('projects.index'));

    expect($project->refresh()->isArchived())->toBeTrue()
        ->and($onlyHere->refresh()->isArchived())->toBeTrue()
        ->and($shared->refresh()->isArchived())->toBeFalse();
});

test('restoring a project brings back only the notes archived with it', function () {
    $project = Project::factory()->create();
    $archivedEarlier = Item::factory()->processed()->create(['archived_at' => now()->subWeek()]);
    $archivedWithProject = Item::factory()->processed()->create();
    $project->items()->attach([$archivedEarlier->id, $archivedWithProject->id]);
    $project->archive();

    $this->delete(route('projects.unarchive', $project))->assertRedirect(route('projects.show', $project));

    expect($project->refresh()->isArchived())->toBeFalse()
        ->and($archivedWithProject->refresh()->isArchived())->toBeFalse()
        ->and($archivedEarlier->refresh()->isArchived())->toBeTrue();
});

test('an item can be archived and restored', function () {
    $item = Item::factory()->processed()->create();

    $this->post(route('items.archive', $item))->assertRedirect();
    expect($item->refresh()->isArchived())->toBeTrue();

    $this->delete(route('items.unarchive', $item))->assertRedirect();
    expect($item->refresh()->isArchived())->toBeFalse();
});

test('the inbox hides archived notes unless the archive filter is on', function () {
    Item::factory()->processed()->create(['title' => 'Current note']);
    Item::factory()->processed()->create(['title' => 'Old note', 'archived_at' => now()]);

    $this->get(route('items.index'))->assertSee('Current note')->assertDontSee('Old note');
    $this->get(route('items.index', ['archived' => 1]))->assertSee('Old note')->assertDontSee('Current note');
});

test('the inbox filters on project and on unfiled notes', function () {
    $project = Project::factory()->create();
    $filed = Item::factory()->processed()->create(['title' => 'Filed note']);
    $project->items()->attach($filed->id);
    Item::factory()->processed()->create(['title' => 'Loose note']);

    $this->get(route('items.index', ['project' => $project->slug]))->assertSee('Filed note')->assertDontSee('Loose note');
    $this->get(route('items.index', ['unfiled' => 1]))->assertSee('Loose note')->assertDontSee('Filed note');
});

test('editing an item sets its projects by hand', function () {
    Queue::fake();
    $item = Item::factory()->processed()->create();
    $aiChoice = Project::factory()->create();
    $chosen = Project::factory()->area()->create();
    $item->projects()->attach($aiChoice->id, ['source' => 'ai']);

    $this->patch(route('items.update', $item), [
        'title' => $item->title,
        'type' => 'note',
        'projects' => [$chosen->id],
    ])->assertRedirect(route('items.show', $item));

    expect($item->projects()->pluck('projects.id')->all())->toBe([$chosen->id])
        ->and($item->projects->sole()->pivot->source)->toBe(AssignmentSource::User);
});

test('today leaves out actions of archived notes and puts project actions first', function () {
    $project = Project::factory()->create(['name' => 'Bathroom']);
    $loose = ItemAction::factory()->for(Item::factory()->processed())->create(['text' => 'Loose action']);
    $inProject = ItemAction::factory()->for(Item::factory()->processed())->create(['text' => 'Project action']);
    $project->items()->attach($inProject->item_id);
    ItemAction::factory()->for(Item::factory()->processed()->state(['archived_at' => now()]))->create(['text' => 'Archived action']);

    $this->get(route('today'))
        ->assertSeeInOrder(['Project action', 'Bathroom', 'Loose action'])
        ->assertDontSee('Archived action');
});
