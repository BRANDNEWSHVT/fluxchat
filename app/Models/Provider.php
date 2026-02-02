<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $api_key
 * @property string|null $base_url
 * @property array<string, mixed>|null $extra_config
 * @property bool $is_active
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiModel> $models
 */
final class Provider extends Model
{
    /** @use HasFactory<\Database\Factories\ProviderFactory> */
    use HasFactory;

    public const NAME_ANTHROPIC = 'anthropic';

    public const NAME_OPENAI = 'openai';

    public const NAME_GEMINI = 'gemini';

    public const NAME_OLLAMA = 'ollama';

    public const NAME_CLIPROXY = 'cliproxy';

    public const NAMES = [
        self::NAME_ANTHROPIC,
        self::NAME_OPENAI,
        self::NAME_GEMINI,
        self::NAME_OLLAMA,
        self::NAME_CLIPROXY,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'base_url',
        'extra_config',
        'is_active',
        'is_default',
    ];

    /**
     * Encrypt the API key before storing.
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt the API key when retrieving.
     */
    public function getApiKeyAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get masked API key for display (shows last 4 characters).
     */
    public function getMaskedApiKeyAttribute(): ?string
    {
        $key = $this->api_key;

        if (! $key) {
            return null;
        }

        $length = mb_strlen($key);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).mb_substr($key, -4);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Get available models for this provider.
     *
     * @return HasMany<AiModel, $this>
     */
    public function availableModels(): HasMany
    {
        return $this->models()->where('is_available', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'extra_config' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
