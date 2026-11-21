<?php

namespace Database\Factories;

use App\Models\AiRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRequest>
 */
class AiRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'agent',
            'name' => 'ItemAnalyzer',
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'duration_ms' => 1500,
            'request' => fake()->sentence(),
            'response' => fake()->sentence(),
            'created_at' => now(),
        ];
    }

    public function failed(string $message = 'AI provider [gemini] is overloaded.'): static
    {
        return $this->state(fn () => ['error' => $message, 'response' => null, 'input_tokens' => 0, 'output_tokens' => 0]);
    }
}
