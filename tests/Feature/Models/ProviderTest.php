<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('provider can be created with factory', function () {
    $provider = Provider::factory()->for($this->user)->anthropic()->create();

    expect($provider)->toBeInstanceOf(Provider::class)
        ->and($provider->name)->toBe(Provider::NAME_ANTHROPIC)
        ->and($provider->user_id)->toBe($this->user->id)
        ->and($provider->is_active)->toBeTrue();
});

test('api key is encrypted when stored', function () {
    $apiKey = 'sk-test-secret-key-12345';

    $provider = Provider::factory()->for($this->user)->create([
        'api_key' => $apiKey,
    ]);

    $rawValue = $provider->getAttributes()['api_key'];
    expect($rawValue)->not->toBe($apiKey);
    expect(Crypt::decryptString($rawValue))->toBe($apiKey);
});

test('api key is decrypted when retrieved', function () {
    $apiKey = 'sk-test-another-secret-key';

    $provider = Provider::factory()->for($this->user)->create([
        'api_key' => $apiKey,
    ]);

    expect($provider->api_key)->toBe($apiKey);
});

test('masked api key shows only last 4 characters', function () {
    $apiKey = 'sk-test-12345678';

    $provider = Provider::factory()->for($this->user)->create([
        'api_key' => $apiKey,
    ]);

    $masked = $provider->masked_api_key;
    expect($masked)->toEndWith('5678')
        ->and(str_starts_with($masked, '****'))->toBeTrue();
});

test('provider has many models', function () {
    $provider = Provider::factory()->for($this->user)->create();
    $models = AiModel::factory()->count(3)->for($provider)->create();

    expect($provider->models)->toHaveCount(3)
        ->and($provider->models->first())->toBeInstanceOf(AiModel::class);
});

test('available models filters by is_available', function () {
    $provider = Provider::factory()->for($this->user)->create();
    AiModel::factory()->count(2)->for($provider)->create(['is_available' => true]);
    AiModel::factory()->count(1)->for($provider)->create(['is_available' => false]);

    expect($provider->availableModels)->toHaveCount(2);
});

test('provider belongs to user', function () {
    $provider = Provider::factory()->for($this->user)->create();

    expect($provider->user)->toBeInstanceOf(User::class)
        ->and($provider->user->id)->toBe($this->user->id);
});

test('user can have multiple providers', function () {
    Provider::factory()->for($this->user)->anthropic()->create();
    Provider::factory()->for($this->user)->openai()->create();
    Provider::factory()->for($this->user)->gemini()->create();

    $providers = Provider::where('user_id', $this->user->id)->get();
    expect($providers)->toHaveCount(3);
});

test('ollama provider can have null api key', function () {
    $provider = Provider::factory()->for($this->user)->ollama()->create([
        'api_key' => null,
        'base_url' => 'http://localhost:11434',
    ]);

    expect($provider->api_key)->toBeNull()
        ->and($provider->base_url)->toBe('http://localhost:11434');
});
