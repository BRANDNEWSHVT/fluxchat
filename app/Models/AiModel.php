<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $provider_id
 * @property string $model_id
 * @property string $display_name
 * @property int|null $context_window
 * @property bool $supports_vision
 * @property bool $supports_streaming
 * @property float|null $pricing_input
 * @property float|null $pricing_output
 * @property bool $is_available
 * @property-read Provider $provider
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Message> $messages
 */
final class AiModel extends Model
{
    /** @use HasFactory<\Database\Factories\AiModelFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'model_id',
        'display_name',
        'context_window',
        'supports_vision',
        'supports_streaming',
        'pricing_input',
        'pricing_output',
        'is_available',
    ];

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->provider->name.' / '.$this->display_name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'supports_vision' => 'boolean',
            'supports_streaming' => 'boolean',
            'pricing_input' => 'decimal:6',
            'pricing_output' => 'decimal:6',
            'is_available' => 'boolean',
        ];
    }
}
