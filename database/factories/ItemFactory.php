<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raw' => fake()->paragraph(),
            'status' => ItemStatus::Pending,
        ];
    }

    /**
     * An item that went through the AI pipeline.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ItemType::Note,
            'type_confidence' => 0.9,
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'body' => $attributes['raw'],
            'status' => ItemStatus::Ready,
            'processed_at' => now(),
        ]);
    }

    /**
     * An item waiting for the author to clarify it.
     */
    public function needsFeedback(): static
    {
        return $this->processed()->state(fn () => [
            'status' => ItemStatus::NeedsFeedback,
            'clarification_question' => 'What does this refer to?',
        ]);
    }

    /**
     * An item with the given embedding.
     *
     * @param  array<int, float>  $embedding
     */
    public function withEmbedding(array $embedding): static
    {
        return $this->state(fn () => ['embedding' => $embedding]);
    }
}
