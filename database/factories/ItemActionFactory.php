<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemAction>
 */
class ItemActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory()->processed(),
            'text' => fake()->sentence(4),
        ];
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn () => ['due_on' => $date]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['done_at' => now()]);
    }
}
