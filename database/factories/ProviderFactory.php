<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provider>
 */
final class ProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(Provider::NAMES),
            'api_key' => 'sk-test-'.fake()->sha256(),
            'base_url' => null,
            'extra_config' => null,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function anthropic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Provider::NAME_ANTHROPIC,
        ]);
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Provider::NAME_OPENAI,
        ]);
    }

    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Provider::NAME_GEMINI,
        ]);
    }

    public function ollama(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Provider::NAME_OLLAMA,
            'api_key' => null,
            'base_url' => 'http://localhost:11434',
        ]);
    }

    public function cliproxy(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Provider::NAME_CLIPROXY,
            'base_url' => 'https://api.example.com',
            'extra_config' => ['headers' => ['X-Custom-Header' => 'value']],
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
