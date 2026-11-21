<?php

use App\Ai\Agents\TagCurator;
use App\Enums\AssignmentSource;
use App\Models\Item;
use App\Models\Tag;
use Laravel\Ai\Prompts\AgentPrompt;

function tagNamed(string $name): Tag
{
    return Tag::factory()->create(['name' => $name, 'slug' => $name]);
}

test('the tags page lists tags with usage counts', function () {
    $tag = tagNamed('film');
    Item::factory()->processed()->count(2)->create()->each(fn (Item $item) => $item->tags()->attach($tag, ['source' => 'ai']));

    $this->get(route('tags.index'))->assertOk()->assertSee('#film')->assertSeeInOrder(['#film', '2']);
});

test('merging moves items to the target tag without duplicates', function () {
    $film = tagNamed('film');
    $films = tagNamed('films');
    $both = Item::factory()->processed()->create();
    $both->tags()->attach([$film->id => ['source' => 'ai'], $films->id => ['source' => 'user']]);
    $onlyFilms = Item::factory()->processed()->create();
    $onlyFilms->tags()->attach($films, ['source' => 'user']);

    $this->post(route('tags.merge'), ['target_id' => $film->id, 'source_ids' => [$films->id]])
        ->assertRedirect(route('tags.index'))
        ->assertSessionHas('status', '#films merged into #film.');

    expect(Tag::pluck('name')->all())->toBe(['film'])
        ->and($both->tags()->pluck('name')->all())->toBe(['film'])
        ->and($onlyFilms->tags()->sole()->pivot->source)->toBe(AssignmentSource::User);
});

test('a tag cannot be merged into itself', function () {
    $film = tagNamed('film');

    $this->post(route('tags.merge'), ['target_id' => $film->id, 'source_ids' => [$film->id]])
        ->assertSessionHasErrors('source_ids.0');

    expect(Tag::count())->toBe(1);
});

test('the ai proposes merges that are shown for review', function () {
    $health = tagNamed('health');
    $healthcare = tagNamed('healthcare');
    tagNamed('laravel');
    TagCurator::fake([[
        'merges' => [
            ['target' => 'health', 'sources' => ['healthcare', 'does-not-exist'], 'reason' => 'Synonyms.'],
            ['target' => 'made-up', 'sources' => ['laravel'], 'reason' => 'Nonsense.'],
        ],
    ]]);

    $this->post(route('tags.suggestions'))
        ->assertRedirect(route('tags.index'))
        ->assertSessionHas('tag_suggestions', [['target_id' => $health->id, 'source_ids' => [$healthcare->id], 'reason' => 'Synonyms.']]);

    $this->get(route('tags.index'))->assertSee('Synonyms.')->assertDontSee('Nonsense.');

    TagCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('health (0)') && $prompt->contains('laravel (0)'));
});

test('suggestions are skipped when there are too few tags', function () {
    TagCurator::fake()->preventStrayPrompts();
    tagNamed('only');

    $this->post(route('tags.suggestions'))->assertSessionHas('status', 'There are too few tags to tidy up yet.');

    TagCurator::assertNeverPrompted();
});
