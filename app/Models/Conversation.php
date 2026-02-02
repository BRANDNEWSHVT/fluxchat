<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $folder_id
 * @property string|null $title
 * @property bool $is_archived
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Folder|null $folder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Message> $messages
 */
final class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'folder_id',
        'title',
        'is_archived',
        'last_message_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public function updateLastMessageAt(): void
    {
        $this->update(['last_message_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }
}
