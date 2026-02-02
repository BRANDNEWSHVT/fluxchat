<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\Provider;
use App\Services\LLM\OllamaService;
use Illuminate\Console\Command;

final class SyncModelsCommand extends Command
{
    private const DEFAULT_MODELS = [
        Provider::NAME_ANTHROPIC => [
            ['model_id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'claude-3-7-sonnet-20250219', 'display_name' => 'Claude 3.7 Sonnet', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'claude-3-5-sonnet-20241022', 'display_name' => 'Claude 3.5 Sonnet', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'claude-3-5-haiku-20241022', 'display_name' => 'Claude 3.5 Haiku', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'claude-3-opus-20240229', 'display_name' => 'Claude 3 Opus', 'context_window' => 200000, 'supports_vision' => true],
        ],
        Provider::NAME_OPENAI => [
            ['model_id' => 'gpt-4.1', 'display_name' => 'GPT-4.1', 'context_window' => 1047576, 'supports_vision' => true],
            ['model_id' => 'gpt-4.1-mini', 'display_name' => 'GPT-4.1 Mini', 'context_window' => 1047576, 'supports_vision' => true],
            ['model_id' => 'gpt-4.1-nano', 'display_name' => 'GPT-4.1 Nano', 'context_window' => 1047576, 'supports_vision' => true],
            ['model_id' => 'gpt-4o', 'display_name' => 'GPT-4o', 'context_window' => 128000, 'supports_vision' => true],
            ['model_id' => 'gpt-4o-mini', 'display_name' => 'GPT-4o Mini', 'context_window' => 128000, 'supports_vision' => true],
            ['model_id' => 'o3', 'display_name' => 'o3', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'o3-mini', 'display_name' => 'o3 Mini', 'context_window' => 200000, 'supports_vision' => true],
            ['model_id' => 'o4-mini', 'display_name' => 'o4 Mini', 'context_window' => 200000, 'supports_vision' => true],
        ],
        Provider::NAME_GEMINI => [
            ['model_id' => 'gemini-2.5-pro-preview-06-05', 'display_name' => 'Gemini 2.5 Pro', 'context_window' => 1048576, 'supports_vision' => true],
            ['model_id' => 'gemini-2.5-flash-preview-05-20', 'display_name' => 'Gemini 2.5 Flash', 'context_window' => 1048576, 'supports_vision' => true],
            ['model_id' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash', 'context_window' => 1048576, 'supports_vision' => true],
            ['model_id' => 'gemini-2.0-flash-lite', 'display_name' => 'Gemini 2.0 Flash Lite', 'context_window' => 1048576, 'supports_vision' => true],
            ['model_id' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro', 'context_window' => 2097152, 'supports_vision' => true],
            ['model_id' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash', 'context_window' => 1048576, 'supports_vision' => true],
        ],
    ];

    protected $signature = 'models:sync 
                            {--provider= : Sync only a specific provider (anthropic, openai, gemini, ollama)}
                            {--user= : Sync models for a specific user ID}';

    protected $description = 'Sync AI models from providers to database';

    public function handle(OllamaService $ollamaService): int
    {
        $providerFilter = $this->option('provider');
        $userId = $this->option('user');

        $query = Provider::query()->where('is_active', true);

        if ($providerFilter) {
            $query->where('name', $providerFilter);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->warn('No active providers found to sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing models for {$providers->count()} provider(s)...");
        $this->newLine();

        foreach ($providers as $provider) {
            $this->syncProviderModels($provider, $ollamaService);
        }

        $this->newLine();
        $this->info('Model sync completed!');

        return self::SUCCESS;
    }

    private function syncProviderModels(Provider $provider, OllamaService $ollamaService): void
    {
        $this->components->task(
            "Syncing {$provider->name} (User #{$provider->user_id})",
            function () use ($provider, $ollamaService) {
                if ($provider->name === Provider::NAME_OLLAMA) {
                    return $this->syncOllamaModels($provider, $ollamaService);
                }

                return $this->syncStaticModels($provider);
            }
        );
    }

    private function syncOllamaModels(Provider $provider, OllamaService $ollamaService): bool
    {
        $result = $ollamaService->syncModels($provider);

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->warn("  ⚠ {$error}");
            }

            return false;
        }

        $this->line("  Synced: {$result['synced']}, Removed: {$result['removed']}");

        return true;
    }

    private function syncStaticModels(Provider $provider): bool
    {
        $models = self::DEFAULT_MODELS[$provider->name] ?? [];

        if (empty($models)) {
            return true;
        }

        $synced = 0;
        foreach ($models as $modelData) {
            AiModel::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'model_id' => $modelData['model_id'],
                ],
                [
                    'display_name' => $modelData['display_name'],
                    'context_window' => $modelData['context_window'],
                    'supports_vision' => $modelData['supports_vision'],
                    'supports_streaming' => true,
                    'is_available' => true,
                ]
            );
            $synced++;
        }

        $this->line("  Synced: {$synced} models");

        return true;
    }
}
