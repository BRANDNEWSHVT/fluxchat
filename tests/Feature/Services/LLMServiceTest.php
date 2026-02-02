<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Provider;
use App\Models\User;
use App\Services\LLM\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('OllamaService', function () {
    it('constructs correct base URL from provider', function () {
        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => 'http://custom-ollama:11434/',
        ]);

        $service = new OllamaService();
        expect($service->getBaseUrl($provider))->toBe('http://custom-ollama:11434');
    });

    it('uses default base URL when not configured', function () {
        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        expect($service->getBaseUrl($provider))->toBe('http://localhost:11434');
    });

    it('tests connection successfully', function () {
        Http::fake([
            'http://localhost:11434' => Http::response('Ollama is running', 200),
        ]);

        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        $result = $service->testConnection($provider);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toBe('Connected to Ollama server');
    });

    it('handles connection failure gracefully', function () {
        Http::fake([
            'http://localhost:11434' => Http::response('', 500),
        ]);

        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        $result = $service->testConnection($provider);

        expect($result['success'])->toBeFalse();
    });

    it('fetches models from Ollama API', function () {
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest', 'size' => 1000000, 'modified_at' => '2024-01-01', 'digest' => 'abc123'],
                    ['name' => 'mistral:7b', 'size' => 2000000, 'modified_at' => '2024-01-01', 'digest' => 'def456'],
                ],
            ], 200),
        ]);

        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        $models = $service->fetchModels($provider);

        expect($models)->toHaveCount(2);
        expect($models[0]['name'])->toBe('llama3.2:latest');
        expect($models[1]['name'])->toBe('mistral:7b');
    });

    it('syncs models from Ollama to database', function () {
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llama3.2:latest', 'size' => 1000000, 'modified_at' => '2024-01-01', 'digest' => 'abc123'],
                    ['name' => 'codellama:7b', 'size' => 2000000, 'modified_at' => '2024-01-01', 'digest' => 'def456'],
                ],
            ], 200),
        ]);

        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        $result = $service->syncModels($provider);

        expect($result['synced'])->toBe(2);
        expect($result['errors'])->toBeEmpty();

        $models = $provider->models()->get();
        expect($models)->toHaveCount(2);

        $llama = $models->firstWhere('model_id', 'llama3.2:latest');
        expect($llama->display_name)->toBe('Llama3.2');
        expect($llama->context_window)->toBe(8192);

        $codellama = $models->firstWhere('model_id', 'codellama:7b');
        expect($codellama->display_name)->toBe('Codellama (7B)');
        expect($codellama->context_window)->toBe(16384);
    });

    it('marks removed models as unavailable', function () {
        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        AiModel::factory()->create([
            'provider_id' => $provider->id,
            'model_id' => 'old-model:latest',
            'is_available' => true,
        ]);

        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'new-model:latest', 'size' => 1000000, 'modified_at' => '2024-01-01', 'digest' => 'abc123'],
                ],
            ], 200),
        ]);

        $service = new OllamaService();
        $result = $service->syncModels($provider);

        expect($result['removed'])->toBe(1);

        $oldModel = AiModel::where('model_id', 'old-model:latest')->first();
        expect($oldModel->is_available)->toBeFalse();
    });

    it('detects vision-capable models', function () {
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'llava:latest', 'size' => 1000000, 'modified_at' => '2024-01-01', 'digest' => 'abc123'],
                    ['name' => 'llama3:latest', 'size' => 2000000, 'modified_at' => '2024-01-01', 'digest' => 'def456'],
                ],
            ], 200),
        ]);

        $provider = Provider::factory()->create([
            'user_id' => $this->user->id,
            'name' => Provider::NAME_OLLAMA,
            'base_url' => null,
        ]);

        $service = new OllamaService();
        $service->syncModels($provider);

        $llava = AiModel::where('model_id', 'llava:latest')->first();
        expect($llava->supports_vision)->toBeTrue();

        $llama = AiModel::where('model_id', 'llama3:latest')->first();
        expect($llama->supports_vision)->toBeFalse();
    });
});

describe('LLMService provider mapping', function () {
    it('maps all provider names correctly', function () {
        $providerNames = [
            Provider::NAME_ANTHROPIC,
            Provider::NAME_OPENAI,
            Provider::NAME_GEMINI,
            Provider::NAME_OLLAMA,
            Provider::NAME_CLIPROXY,
        ];

        foreach ($providerNames as $name) {
            expect(fn () => Provider::factory()->create([
                'user_id' => $this->user->id,
                'name' => $name,
            ]))->not->toThrow(Exception::class);
        }
    });
});
