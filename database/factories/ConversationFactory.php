<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
final class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'folder_id' => null,
            'title' => fake()->sentence(4),
            'is_archived' => false,
            'last_message_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function inFolder(Folder $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder_id' => $folder->id,
            'user_id' => $folder->user_id,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    public function untitled(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => now(),
        ]);
    }
}
