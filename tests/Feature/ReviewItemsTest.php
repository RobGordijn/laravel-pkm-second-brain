<?php

use App\Enums\AssignmentSource;
use App\Enums\ItemStatus;
use App\Enums\ProjectKind;
use App\Models\Item;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;

test('the review lists unplaced processed notes, oldest first', function () {
    Item::factory()->processed()->create(['title' => 'Newest', 'created_at' => now()->subDay()]);
    Item::factory()->processed()->create(['title' => 'Oldest', 'created_at' => now()->subWeek()]);
    Item::factory()->processed()->create(['title' => 'Already reviewed', 'reviewed_at' => now()]);
    Item::factory()->processed()->create(['title' => 'Archived note', 'archived_at' => now()]);
    Item::factory()->create(['raw' => 'Still processing', 'status' => ItemStatus::Processing]);

    $this->get(route('review.index'))
        ->assertOk()
        ->assertSeeInOrder(['Oldest', 'Newest'])
        ->assertDontSee('Already reviewed')
        ->assertDontSee('Archived note')
        ->assertDontSee('Still processing');
});

test('the review shows one batch and how many are left', function () {
    Item::factory()->processed()->count(22)->create();

    $response = $this->get(route('review.index'));

    expect($response->viewData('items'))->toHaveCount(20);
    $response->assertSee('You are seeing the oldest 20 of 22.');
});

test('the navigation shows how many notes wait for review', function () {
    Item::factory()->processed()->count(3)->create();

    $this->get(route('today'))->assertSeeInOrder(['Review', '3']);
});

test('the AI project choice is highlighted', function () {
    $project = Project::factory()->create(['name' => 'Bathroom']);
    Project::factory()->create(['name' => 'Anders']);
    $item = Item::factory()->processed()->create();
    $item->projects()->attach($project->id, ['source' => 'ai']);

    $this->get(route('review.index'))->assertSee('Bathroom <span class="opacity-75">· AI suggestion</span>', false)
        ->assertDontSee('Anders <span class="opacity-75">', false);
});

test('placing a note in a project replaces the AI choice and marks it reviewed', function () {
    $aiChoice = Project::factory()->create();
    $chosen = Project::factory()->area()->create();
    $item = Item::factory()->processed()->create();
    $item->projects()->attach($aiChoice->id, ['source' => 'ai']);

    $this->post(route('review.store', $item), ['decision' => 'project', 'project_id' => $chosen->id])
        ->assertRedirect(route('review.index'));

    $item->refresh();
    expect($item->reviewed_at)->not->toBeNull()
        ->and($item->projects->pluck('id')->all())->toBe([$chosen->id])
        ->and($item->projects->sole()->pivot->source)->toBe(AssignmentSource::User);
});

test('a note can be placed in a project created during the review', function () {
    $item = Item::factory()->processed()->create();

    $this->post(route('review.store', $item), ['decision' => 'new_project', 'name' => 'Renovate bathroom', 'kind' => 'project'])
        ->assertRedirect(route('review.index'));

    $project = Project::sole();
    expect($project->name)->toBe('Renovate bathroom')
        ->and($project->kind)->toBe(ProjectKind::Project)
        ->and($item->refresh()->projects->pluck('id')->all())->toBe([$project->id])
        ->and($item->reviewed_at)->not->toBeNull();
});

test('creating a project during the review reuses an active one with the same name', function () {
    $existing = Project::factory()->area()->create(['name' => 'Health']);
    $item = Item::factory()->processed()->create();

    $this->post(route('review.store', $item), ['decision' => 'new_project', 'name' => 'health', 'kind' => 'project']);

    expect(Project::count())->toBe(1)
        ->and($item->refresh()->projects->pluck('id')->all())->toBe([$existing->id]);
});

test('creating a project during the review needs a name and refuses an archived one', function () {
    Project::factory()->archived()->create(['name' => 'Old garden']);
    $item = Item::factory()->processed()->create();

    $this->post(route('review.store', $item), ['decision' => 'new_project', 'kind' => 'area'])->assertSessionHasErrors('name');
    $this->post(route('review.store', $item), ['decision' => 'new_project', 'name' => 'Old garden', 'kind' => 'area'])->assertSessionHasErrors('name');

    expect($item->refresh()->reviewed_at)->toBeNull()
        ->and(Project::count())->toBe(1);
});

test('a note can be kept as reference material without a project', function () {
    $project = Project::factory()->create();
    $item = Item::factory()->processed()->create();
    $item->projects()->attach($project->id, ['source' => 'ai']);

    $this->post(route('review.store', $item), ['decision' => 'resource'])->assertRedirect();

    expect($item->refresh()->reviewed_at)->not->toBeNull()
        ->and($item->projects)->toBeEmpty()
        ->and($item->isArchived())->toBeFalse();
});

test('a note can be archived from the review', function () {
    $item = Item::factory()->processed()->create();

    $this->post(route('review.store', $item), ['decision' => 'archive'])->assertRedirect();

    expect($item->refresh()->isArchived())->toBeTrue()
        ->and($item->reviewed_at)->not->toBeNull();
});

test('a project is required and must be active', function () {
    $item = Item::factory()->processed()->create();
    $archived = Project::factory()->archived()->create();

    $this->post(route('review.store', $item), ['decision' => 'project'])->assertSessionHasErrors('project_id');
    $this->post(route('review.store', $item), ['decision' => 'project', 'project_id' => $archived->id])->assertSessionHasErrors('project_id');

    expect($item->refresh()->reviewed_at)->toBeNull();
});

test('editing or archiving a note elsewhere also counts as reviewed', function () {
    Queue::fake();
    $edited = Item::factory()->processed()->create();
    $archived = Item::factory()->processed()->create();

    $this->patch(route('items.update', $edited), ['title' => 'New', 'type' => 'note', 'why_saved' => 'This is where I left off']);
    $this->post(route('items.archive', $archived));

    expect($edited->refresh()->reviewed_at)->not->toBeNull()
        ->and($edited->why_saved)->toBe('This is where I left off')
        ->and($archived->refresh()->reviewed_at)->not->toBeNull()
        ->and(Item::query()->awaitingReview()->count())->toBe(0);
});
