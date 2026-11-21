<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/**
 * @param  list<float>  $leading
 * @return list<float>
 */
function searchVector(array $leading): array
{
    return array_pad($leading, 1536, 0.0);
}

beforeEach(function () {
    Embeddings::fake(fn (EmbeddingsPrompt $prompt) => array_map(
        fn (string $input) => str_contains($input, 'fruit') ? searchVector([1.0, 0.05]) : searchVector([0.0, 0.0, 1.0]),
        $prompt->inputs,
    ));
});

test('the inbox lists the latest items', function () {
    Item::factory()->processed()->create(['title' => 'First note']);

    $this->get(route('items.index'))->assertOk()->assertSee('First note');
});

test('keyword search finds items by their words', function () {
    Item::factory()->processed()->create(['title' => 'Set up pgvector', 'body' => 'Create HNSW index']);
    Item::factory()->processed()->create(['title' => 'Groceries', 'body' => 'Milk and bread']);

    $this->get(route('items.index', ['q' => 'hnsw']))
        ->assertOk()
        ->assertSee('Set up pgvector')
        ->assertDontSee('Groceries');
});

test('semantic search finds items by meaning without shared words', function () {
    Item::factory()->processed()->withEmbedding(searchVector([1.0, 0.0]))->create(['title' => 'Buy apples and pears']);
    Item::factory()->processed()->withEmbedding(searchVector([0.0, 1.0]))->create(['title' => 'Laravel upgrade']);

    $this->get(route('items.index', ['q' => 'fruit']))
        ->assertOk()
        ->assertSee('Buy apples and pears')
        ->assertDontSee('Laravel upgrade');

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->contains('fruit') && $prompt->dimensions === 1536);
});

test('filters narrow the results', function () {
    $tag = Tag::factory()->create(['name' => 'work', 'slug' => 'work']);
    Item::factory()->processed()->create(['title' => 'With tag'])->tags()->attach($tag, ['source' => 'ai']);
    Item::factory()->processed()->create(['title' => 'Without tag', 'type' => ItemType::Idea]);

    $this->get(route('items.index', ['tag' => 'work']))->assertSee('With tag')->assertDontSee('Without tag');
    $this->get(route('items.index', ['type' => 'idea']))->assertSee('Without tag')->assertDontSee('With tag');
});

test('the fragment header returns only the list', function () {
    Item::factory()->processed()->create(['title' => 'Fragment item']);

    $this->get(route('items.index'), ['X-Fragment' => '1'])
        ->assertOk()
        ->assertSee('Fragment item')
        ->assertDontSee('capture-form');
});
