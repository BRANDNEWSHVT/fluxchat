<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Models\AiModel;
use App\Models\Provider;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OllamaService
{
    private const DEFAULT_BASE_URL = 'http://localhost:11434';

    private const TIMEOUT_SECONDS = 10;

    public function getBaseUrl(Provider $provider): string
    {
        return mb_rtrim($provider->base_url ?: self::DEFAULT_BASE_URL, '/');
    }

    /**
     * @return array{success: bool, message: string, version?: string}
     */
    public function testConnection(Provider $provider): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get($this->getBaseUrl($provider));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connected to Ollama server',
                    'version' => $response->body(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Ollama server returned error: '.$response->status(),
            ];
        } catch (ConnectionException) {
            return [
                'success' => false,
                'message' => 'Cannot connect to Ollama server. Is it running?',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array{name: string, size: int, modified_at: string, digest: string}>
     */
    public function fetchModels(Provider $provider): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get($this->getBaseUrl($provider).'/api/tags');

            if ($response->successful()) {
                $data = $response->json();

                return $data['models'] ?? [];
            }

            Log::warning('Ollama API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (Exception $e) {
            Log::error('Ollama connection error', [
                'message' => $e->getMessage(),
                'provider_id' => $provider->id,
            ]);

            return [];
        }
    }

    /**
     * @return array{synced: int, removed: int, errors: array<string>}
     */
    public function syncModels(Provider $provider): array
    {
        $result = [
            'synced' => 0,
            'removed' => 0,
            'errors' => [],
        ];

        $ollamaModels = $this->fetchModels($provider);

        if (empty($ollamaModels)) {
            $result['errors'][] = 'No models found or cannot connect to Ollama server';

            return $result;
        }

        $existingModelIds = $provider->models()->pluck('model_id')->toArray();
        $fetchedModelIds = [];

        foreach ($ollamaModels as $model) {
            $modelId = $model['name'];
            $fetchedModelIds[] = $modelId;

            $displayName = $this->formatDisplayName($modelId);
            $supportsVision = $this->modelSupportsVision($modelId);

            try {
                AiModel::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'model_id' => $modelId,
                    ],
                    [
                        'display_name' => $displayName,
                        'context_window' => $this->guessContextWindow($modelId),
                        'supports_vision' => $supportsVision,
                        'supports_streaming' => true,
                        'is_available' => true,
                    ]
                );
                $result['synced']++;
            } catch (Exception $e) {
                $result['errors'][] = "Failed to sync model {$modelId}: ".$e->getMessage();
            }
        }

        $removedCount = $provider->models()
            ->whereNotIn('model_id', $fetchedModelIds)
            ->update(['is_available' => false]);

        $result['removed'] = $removedCount;

        return $result;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function pullModel(Provider $provider, string $modelName): array
    {
        try {
            $response = Http::timeout(300)
                ->post($this->getBaseUrl($provider).'/api/pull', [
                    'name' => $modelName,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Model {$modelName} pulled successfully",
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to pull model: '.$response->body(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error pulling model: '.$e->getMessage(),
            ];
        }
    }

    private function formatDisplayName(string $modelId): string
    {
        $parts = explode(':', $modelId);
        $name = $parts[0];
        $tag = $parts[1] ?? 'latest';

        $formattedName = ucfirst(str_replace(['-', '_'], ' ', $name));

        if ($tag !== 'latest') {
            $formattedName .= ' ('.mb_strtoupper($tag).')';
        }

        return $formattedName;
    }

    private function modelSupportsVision(string $modelId): bool
    {
        $visionModels = ['llava', 'bakllava', 'moondream', 'cogvlm'];
        $modelLower = mb_strtolower($modelId);

        foreach ($visionModels as $visionModel) {
            if (str_contains($modelLower, $visionModel)) {
                return true;
            }
        }

        return false;
    }

    private function guessContextWindow(string $modelId): int
    {
        $modelLower = mb_strtolower($modelId);

        if (str_contains($modelLower, '128k')) {
            return 128000;
        }
        if (str_contains($modelLower, '32k')) {
            return 32000;
        }
        if (str_contains($modelLower, '16k')) {
            return 16000;
        }

        $contextWindows = [
            'llama3' => 8192,
            'llama2' => 4096,
            'mistral' => 8192,
            'mixtral' => 32768,
            'codellama' => 16384,
            'gemma' => 8192,
            'phi' => 2048,
            'qwen' => 32768,
        ];

        foreach ($contextWindows as $family => $contextWindow) {
            if (str_contains($modelLower, $family)) {
                return $contextWindow;
            }
        }

        return 4096;
    }
}
