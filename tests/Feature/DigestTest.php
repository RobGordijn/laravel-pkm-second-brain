<?php

use App\Ai\Agents\DigestWriter;
use App\Enums\AssignmentSource;
use App\Enums\ProjectKind;
use App\Jobs\GenerateDigest;
use App\Models\Digest;
use App\Models\Item;
use App\Models\ItemAction;
use App\Models\Project;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

test('it writes an overview of recent notes, open actions and open questions', function () {
    $this->travelTo('2026-11-18 09:00');
    $recent = Item::factory()->processed()->create(['title' => 'Insulate the attic', 'created_at' => now()->subDays(2)]);
    Item::factory()->processed()->create(['title' => 'Old note', 'created_at' => now()->subMonth()]);
    ItemAction::factory()->for($recent)->dueOn('2026-11-10')->create(['text' => 'Request quotes']);
    ItemAction::factory()->for($recent)->done()->create(['text' => 'Already done']);
    Item::factory()->needsFeedback()->create(['clarification_question' => 'Which Jan?', 'created_at' => now()->subMonth()]);
    DigestWriter::fake([['content' => "**This week**\n\n- Attic [#{$recent->id}]", 'project_suggestions' => []]]);

    GenerateDigest::dispatchSync();

    $digest = Digest::sole();
    expect($digest->period_start->toDateString())->toBe('2026-11-12')
        ->and($digest->content)->toContain('**This week**')
        ->and($digest->stats)->toBe(['new_items' => 1, 'open_actions' => 1, 'overdue_actions' => 1, 'needs_feedback' => 1]);

    DigestWriter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains("[#{$recent->id}]")
        && $prompt->contains('Insulate the attic')
        && ! $prompt->contains('Old note')
        && $prompt->contains('Request quotes (deadline 2026-11-10, OVERDUE)')
        && ! $prompt->contains('Already done')
        && $prompt->contains('Which Jan?'));
});

test('a quiet period does not call the AI', function () {
    DigestWriter::fake()->preventStrayPrompts();

    GenerateDigest::dispatchSync();

    expect(Digest::sole()->content)->toContain('A quiet period');
    DigestWriter::assertNeverPrompted();
});

test('the command generates a digest for the given number of days', function () {
    DigestWriter::fake()->preventStrayPrompts();

    $this->artisan('pkm:digest', ['--days' => 14])->assertSuccessful();

    expect(Digest::sole()->period_start->toDateString())->toBe(today()->subDays(13)->toDateString());
});

test('the digest runs weekly on monday morning', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'pkm:digest'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 8 * * 1');
});

test('the overview page shows the latest digest with linked notes', function () {
    $item = Item::factory()->processed()->create();
    Digest::factory()->create(['content' => 'Old digest']);
    Digest::factory()->create(['content' => "**Loose ends**\n\n- Call the dentist [#{$item->id}]"]);

    $this->get(route('digests.index'))
        ->assertOk()
        ->assertSee('Loose ends')
        ->assertSee('href="'.route('items.show', $item).'"', false)
        ->assertDontSee('Old digest')
        ->assertSee('Earlier digests');
});

test('an overview can be requested from the page', function () {
    Queue::fake();

    $this->post(route('digests.store'))->assertRedirect(route('digests.index'));

    Queue::assertPushed(GenerateDigest::class);
});

test('requesting an overview twice queues it only once', function () {
    Queue::fake();

    $this->post(route('digests.store'));
    $this->post(route('digests.store'));

    Queue::assertPushedTimes(GenerateDigest::class, 1);
});

test('the overview page explains when there is no digest yet', function () {
    $this->get(route('digests.index'))->assertOk()->assertSee('No digest yet');
});

test('grouped note references become separate links', function () {
    [$first, $second] = Item::factory()->processed()->count(2)->create();
    Digest::factory()->create(['content' => "- Travel [#{$first->id}, #{$second->id}, #999999]"]);

    $this->get(route('digests.index'))
        ->assertSee('href="'.route('items.show', $first).'"', false)
        ->assertSee('href="'.route('items.show', $second).'"', false)
        ->assertSee('[#999999]');
});

test('it keeps project suggestions that refer to at least two notes the AI was shown', function () {
    [$first, $second] = Item::factory()->processed()->count(2)->create();
    $old = Item::factory()->processed()->create(['created_at' => now()->subMonth()]);
    Project::factory()->create(['name' => 'Bathroom', 'goal' => 'New bathroom']);
    DigestWriter::fake([[
        'content' => '**This week**',
        'project_suggestions' => [
            ['name' => ' Insulate  the attic ', 'kind' => 'project', 'goal' => 'Get the attic warm', 'item_ids' => [$first->id, $second->id, 999999]],
            ['name' => 'Too small', 'kind' => 'area', 'goal' => '', 'item_ids' => [$first->id, $old->id]],
        ],
    ]]);

    GenerateDigest::dispatchSync();

    expect(Digest::sole()->project_suggestions)->toBe([
        ['name' => 'Insulate the attic', 'kind' => 'project', 'goal' => 'Get the attic warm', 'item_ids' => [$first->id, $second->id]],
    ]);
    DigestWriter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('- Bathroom (project): New bathroom'));
});

test('the overview page offers to turn a suggested group into a project', function () {
    [$first, $second] = Item::factory()->processed()->count(2)->create();
    $digest = Digest::factory()->create(['project_suggestions' => [
        ['name' => 'Insulate the attic', 'kind' => 'project', 'goal' => 'Get the attic warm', 'item_ids' => [$first->id, $second->id]],
    ]]);

    $this->get(route('digests.show', $digest))
        ->assertOk()
        ->assertSee('These belong together')
        ->assertSee('Get the attic warm')
        ->assertSee($first->title)
        ->assertSee('Create project');
});

test('accepting a suggestion creates the project with its notes and remembers it', function () {
    [$first, $second] = Item::factory()->processed()->count(2)->create();
    $digest = Digest::factory()->create(['project_suggestions' => [
        ['name' => 'Insulate the attic', 'kind' => 'area', 'goal' => 'Keep the attic warm', 'item_ids' => [$first->id, $second->id, 999999]],
    ]]);

    $this->post(route('digests.projects.store', [$digest, 0]), ['name' => 'Attic'])
        ->assertRedirect(route('projects.show', 'attic'));

    $project = Project::sole();
    expect($project->name)->toBe('Attic')
        ->and($project->kind)->toBe(ProjectKind::Area)
        ->and($project->goal)->toBe('Keep the attic warm')
        ->and($project->items()->pluck('items.id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($project->items->pluck('pivot.source')->unique()->all())->toBe([AssignmentSource::User])
        ->and($digest->refresh()->project_suggestions[0]['project_id'])->toBe($project->id);

    $this->get(route('digests.show', $digest))->assertSee('Filed under')->assertDontSee('Create area');
});

test('accepting a suggestion with an existing name adds the notes to that project as confirmed by the author', function () {
    $project = Project::factory()->create(['name' => 'Bathroom']);
    $alreadyThere = Item::factory()->processed()->create();
    $project->items()->attach($alreadyThere->id, ['source' => 'ai']);
    $new = Item::factory()->processed()->create();
    $digest = Digest::factory()->create(['project_suggestions' => [
        ['name' => 'Bathroom', 'kind' => 'project', 'goal' => '', 'item_ids' => [$alreadyThere->id, $new->id]],
    ]]);

    $this->get(route('digests.show', $digest))->assertSee('Add to project');
    $this->post(route('digests.projects.store', [$digest, 0]), ['name' => 'bathroom'])
        ->assertRedirect(route('projects.show', $project));

    expect(Project::count())->toBe(1)
        ->and($project->items()->count())->toBe(2)
        ->and($project->items()->whereKey($alreadyThere->id)->first()->pivot->source)->toBe(AssignmentSource::User);
});

test('an unknown suggestion returns not found', function () {
    $digest = Digest::factory()->create();

    $this->post(route('digests.projects.store', [$digest, 0]), ['name' => 'Something'])->assertNotFound();
});

test('a suggestion is not added to an archived project with the same name', function () {
    $archived = Project::factory()->archived()->create(['name' => 'Bathroom']);
    [$first, $second] = Item::factory()->processed()->count(2)->create();
    $digest = Digest::factory()->create(['project_suggestions' => [
        ['name' => 'Bathroom', 'kind' => 'project', 'goal' => '', 'item_ids' => [$first->id, $second->id]],
    ]]);

    $this->post(route('digests.projects.store', [$digest, 0]), ['name' => 'Bathroom'])
        ->assertSessionHasErrorsIn('suggestion0', 'name');

    expect($archived->items()->count())->toBe(0)
        ->and($digest->refresh()->project_suggestions[0])->not->toHaveKey('project_id');
});
