<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $sort_order
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 */
final class Folder extends Model
{
    /** @use HasFactory<\Database\Factories\FolderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'sort_order',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->orderByDesc('last_message_at');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
