<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
final class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'ai_model_id' => null,
            'role' => Message::ROLE_USER,
            'content' => fake()->paragraph(),
            'attachments' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'metadata' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Message::ROLE_USER,
            'ai_model_id' => null,
        ]);
    }

    public function assistant(?AiModel $model = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Message::ROLE_ASSISTANT,
            'ai_model_id' => $model?->id ?? AiModel::factory(),
            'input_tokens' => fake()->numberBetween(10, 1000),
            'output_tokens' => fake()->numberBetween(50, 2000),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Message::ROLE_SYSTEM,
            'ai_model_id' => null,
        ]);
    }

    public function withAttachments(array $attachments = []): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => $attachments ?: [
                ['type' => 'image', 'url' => 'https://example.com/image.png', 'name' => 'image.png'],
            ],
        ]);
    }

    public function withTokens(int $input = 100, int $output = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'input_tokens' => $input,
            'output_tokens' => $output,
        ]);
    }
}
