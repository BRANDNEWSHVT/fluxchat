<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiModel>
 */
final class AiModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'model_id' => 'model-'.fake()->unique()->slug(2),
            'display_name' => fake()->words(3, true),
            'context_window' => fake()->randomElement([8192, 16384, 32768, 128000, 200000]),
            'supports_vision' => fake()->boolean(30),
            'supports_streaming' => true,
            'pricing_input' => fake()->randomFloat(6, 0.001, 0.03),
            'pricing_output' => fake()->randomFloat(6, 0.002, 0.06),
            'is_available' => true,
        ];
    }

    public function claude35Sonnet(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_id' => 'claude-3-5-sonnet-20241022',
            'display_name' => 'Claude 3.5 Sonnet',
            'context_window' => 200000,
            'supports_vision' => true,
            'pricing_input' => 0.003,
            'pricing_output' => 0.015,
        ]);
    }

    public function claude3Opus(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_id' => 'claude-3-opus-20240229',
            'display_name' => 'Claude 3 Opus',
            'context_window' => 200000,
            'supports_vision' => true,
            'pricing_input' => 0.015,
            'pricing_output' => 0.075,
        ]);
    }

    public function claude3Haiku(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_id' => 'claude-3-haiku-20240307',
            'display_name' => 'Claude 3 Haiku',
            'context_window' => 200000,
            'supports_vision' => true,
            'pricing_input' => 0.00025,
            'pricing_output' => 0.00125,
        ]);
    }

    public function gpt4o(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_id' => 'gpt-4o',
            'display_name' => 'GPT-4o',
            'context_window' => 128000,
            'supports_vision' => true,
            'pricing_input' => 0.005,
            'pricing_output' => 0.015,
        ]);
    }

    public function gemini15Pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_id' => 'gemini-1.5-pro',
            'display_name' => 'Gemini 1.5 Pro',
            'context_window' => 2000000,
            'supports_vision' => true,
            'pricing_input' => 0.00125,
            'pricing_output' => 0.005,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    public function withoutVision(): static
    {
        return $this->state(fn (array $attributes) => [
            'supports_vision' => false,
        ]);
    }
}
