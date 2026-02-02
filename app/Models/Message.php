<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $ai_model_id
 * @property string $role
 * @property string $content
 * @property array<string, mixed>|null $attachments
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property array<string, mixed>|null $metadata
 * @property-read Conversation $conversation
 * @property-read AiModel|null $aiModel
 */
final class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
    ];

    protected $fillable = [
        'conversation_id',
        'ai_model_id',
        'role',
        'content',
        'attachments',
        'input_tokens',
        'output_tokens',
        'metadata',
    ];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isAssistant(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    public function isSystem(): bool
    {
        return $this->role === self::ROLE_SYSTEM;
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }
}
