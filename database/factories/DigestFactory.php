<?php

namespace Database\Factories;

use App\Models\Digest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Digest>
 */
class DigestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_start' => today()->subWeek()->toDateString(),
            'period_end' => today()->toDateString(),
            'content' => "**This week**\n\n".fake()->sentence(),
            'stats' => ['new_items' => 3, 'open_actions' => 2, 'overdue_actions' => 1, 'needs_feedback' => 0],
        ];
    }
}
