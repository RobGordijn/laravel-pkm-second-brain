<?php

namespace Database\Factories;

use App\Enums\ProjectKind;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'kind' => ProjectKind::Project,
            'goal' => fake()->sentence(),
        ];
    }

    /**
     * An ongoing area of responsibility instead of a project.
     */
    public function area(): static
    {
        return $this->state(fn () => ['kind' => ProjectKind::Area, 'goal' => null]);
    }

    /**
     * A project that is no longer active.
     */
    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
