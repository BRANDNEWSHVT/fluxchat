<?php

use App\Models\AiModel;
use App\Models\Provider;
use App\Services\LLM\CliproxyService;
use App\Services\LLM\OllamaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('kitchen.settings.providers');

new class extends Component
{
    public bool $showAddModal = false;

    public bool $showEditModal = false;

    public bool $showAddModelModal = false;

    public bool $showEditModelModal = false;

    public string $providerName = '';

    #[Validate('required|string|min:1')]
    public string $apiKey = '';

    public string $baseUrl = '';

    public ?int $editingProviderId = null;

    public ?int $editingModelId = null;

    public ?int $addModelProviderId = null;

    public bool $isSyncing = false;

    public bool $isTesting = false;

    public array $customHeaders = [];

    public string $newHeaderKey = '';

    public string $newHeaderValue = '';

    public string $modelId = '';

    public string $modelDisplayName = '';

    public int $modelContextWindow = 128000;

    public bool $modelSupportsVision = false;

    public bool $modelSupportsStreaming = true;

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    #[Computed]
    public function providers()
    {
        return Provider::query()
            ->where('user_id', $this->user->id)
            ->with('models')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableProviders(): array
    {
        $configured = $this->providers->pluck('name')->toArray();

        return array_filter(Provider::NAMES, fn ($name) => ! in_array($name, $configured));
    }

    public function openAddModal(string $name): void
    {
        $this->providerName = $name;
        $this->apiKey = '';
        $this->customHeaders = [];

        $this->baseUrl = match ($name) {
            Provider::NAME_OLLAMA => 'http://localhost:11434',
            Provider::NAME_CLIPROXY => 'http://localhost:8317',
            default => '',
        };

        $this->showAddModal = true;
    }

    public function openEditModal(int $id): void
    {
        $provider = Provider::find($id);
        if ($provider && $provider->user_id === $this->user->id) {
            $this->editingProviderId = $id;
            $this->providerName = $provider->name;
            $this->apiKey = '';
            $this->baseUrl = $provider->base_url ?? '';
            $this->customHeaders = $provider->extra_config['headers'] ?? [];
            $this->showEditModal = true;
        }
    }

    public function closeModals(): void
    {
        $this->showAddModal = false;
        $this->showEditModal = false;
        $this->showAddModelModal = false;
        $this->showEditModelModal = false;
        $this->editingProviderId = null;
        $this->editingModelId = null;
        $this->addModelProviderId = null;
        $this->reset(['providerName', 'apiKey', 'baseUrl', 'customHeaders', 'newHeaderKey', 'newHeaderValue']);
        $this->resetModelForm();
    }

    public function addHeader(): void
    {
        if (empty(mb_trim($this->newHeaderKey))) {
            return;
        }

        $this->customHeaders[$this->newHeaderKey] = $this->newHeaderValue;
        $this->newHeaderKey = '';
        $this->newHeaderValue = '';
    }

    public function removeHeader(string $key): void
    {
        unset($this->customHeaders[$key]);
    }

    public function addProvider(): void
    {
        $needsApiKey = ! in_array($this->providerName, [Provider::NAME_OLLAMA]);

        if ($needsApiKey && empty($this->apiKey)) {
            $this->addError('apiKey', 'API key is required for this provider.');

            return;
        }

        $extraConfig = [];
        if (! empty($this->customHeaders)) {
            $extraConfig['headers'] = $this->customHeaders;
        }

        $provider = Provider::create([
            'user_id' => $this->user->id,
            'name' => $this->providerName,
            'api_key' => $needsApiKey ? $this->apiKey : null,
            'base_url' => $this->baseUrl ?: null,
            'extra_config' => ! empty($extraConfig) ? $extraConfig : null,
            'is_active' => true,
            'is_default' => $this->providers->count() === 0,
        ]);

        $this->seedDefaultModels($provider);

        $this->closeModals();
        unset($this->providers);
        unset($this->availableProviders);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => ucfirst($this->providerName).' provider added successfully.',
        ]);
    }

    public function updateProvider(): void
    {
        $provider = Provider::find($this->editingProviderId);
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $extraConfig = $provider->extra_config ?? [];
        if (! empty($this->customHeaders)) {
            $extraConfig['headers'] = $this->customHeaders;
        } else {
            unset($extraConfig['headers']);
        }

        $data = [
            'base_url' => $this->baseUrl ?: null,
            'extra_config' => ! empty($extraConfig) ? $extraConfig : null,
        ];

        if (! empty($this->apiKey)) {
            $data['api_key'] = $this->apiKey;
        }

        $provider->update($data);

        $this->closeModals();
        unset($this->providers);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Provider updated successfully.',
        ]);
    }

    public function deleteProvider(int $id): void
    {
        $provider = Provider::find($id);
        if ($provider && $provider->user_id === $this->user->id) {
            $provider->delete();
            unset($this->providers);
            unset($this->availableProviders);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Provider deleted.',
            ]);
        }
    }

    public function setDefault(int $id): void
    {
        Provider::query()
            ->where('user_id', $this->user->id)
            ->update(['is_default' => false]);

        Provider::query()
            ->where('id', $id)
            ->where('user_id', $this->user->id)
            ->update(['is_default' => true]);

        unset($this->providers);
    }

    public function toggleActive(int $id): void
    {
        $provider = Provider::find($id);
        if ($provider && $provider->user_id === $this->user->id) {
            $provider->update(['is_active' => ! $provider->is_active]);
            unset($this->providers);
        }
    }

    public function syncModels(int $id): void
    {
        $provider = Provider::find($id);
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $this->isSyncing = true;

        if ($provider->name === Provider::NAME_OLLAMA) {
            $ollamaService = app(OllamaService::class);
            $result = $ollamaService->syncModels($provider);

            if (! empty($result['errors'])) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => $result['errors'][0] ?? 'Failed to sync models.',
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Synced {$result['synced']} models from Ollama.",
                ]);
            }
        } elseif ($provider->name === Provider::NAME_CLIPROXY) {
            $cliproxyService = app(CliproxyService::class);
            $models = $cliproxyService->fetchModels($provider);

            if (empty($models)) {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => 'No models found. Add models manually or check connection.',
                ]);
            } else {
                $synced = 0;
                foreach ($models as $model) {
                    AiModel::updateOrCreate(
                        [
                            'provider_id' => $provider->id,
                            'model_id' => $model['id'],
                        ],
                        [
                            'display_name' => $model['name'],
                            'context_window' => 128000,
                            'supports_vision' => false,
                            'supports_streaming' => true,
                            'is_available' => true,
                        ]
                    );
                    $synced++;
                }

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Synced {$synced} models from CliproxyAPI.",
                ]);
            }
        } else {
            $this->seedDefaultModels($provider);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Models refreshed.',
            ]);
        }

        $this->isSyncing = false;
        unset($this->providers);
    }

    public function testConnection(int $id): void
    {
        $provider = Provider::find($id);
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $this->isTesting = true;

        if ($provider->name === Provider::NAME_OLLAMA) {
            $ollamaService = app(OllamaService::class);
            $result = $ollamaService->testConnection($provider);
        } elseif ($provider->name === Provider::NAME_CLIPROXY) {
            $cliproxyService = app(CliproxyService::class);
            $result = $cliproxyService->testConnection($provider);
        } else {
            $result = [
                'success' => false,
                'message' => 'Connection test not available for this provider.',
            ];
        }

        $this->isTesting = false;

        $this->dispatch('notify', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function openAddModelModal(int $providerId): void
    {
        $provider = Provider::find($providerId);
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $this->addModelProviderId = $providerId;
        $this->resetModelForm();
        $this->showAddModelModal = true;
    }

    public function openEditModelModal(int $modelId): void
    {
        $model = AiModel::find($modelId);
        if (! $model) {
            return;
        }

        $provider = $model->provider;
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $this->editingModelId = $modelId;
        $this->modelId = $model->model_id;
        $this->modelDisplayName = $model->display_name;
        $this->modelContextWindow = $model->context_window;
        $this->modelSupportsVision = $model->supports_vision;
        $this->modelSupportsStreaming = $model->supports_streaming;
        $this->showEditModelModal = true;
    }

    public function addModel(): void
    {
        if (empty(mb_trim($this->modelId)) || empty(mb_trim($this->modelDisplayName))) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Model ID and display name are required.',
            ]);

            return;
        }

        $provider = Provider::find($this->addModelProviderId);
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        AiModel::create([
            'provider_id' => $provider->id,
            'model_id' => $this->modelId,
            'display_name' => $this->modelDisplayName,
            'context_window' => $this->modelContextWindow,
            'supports_vision' => $this->modelSupportsVision,
            'supports_streaming' => $this->modelSupportsStreaming,
            'is_available' => true,
        ]);

        $this->closeModals();
        unset($this->providers);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Model added successfully.',
        ]);
    }

    public function updateModel(): void
    {
        $model = AiModel::find($this->editingModelId);
        if (! $model) {
            return;
        }

        $provider = $model->provider;
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $model->update([
            'model_id' => $this->modelId,
            'display_name' => $this->modelDisplayName,
            'context_window' => $this->modelContextWindow,
            'supports_vision' => $this->modelSupportsVision,
            'supports_streaming' => $this->modelSupportsStreaming,
        ]);

        $this->closeModals();
        unset($this->providers);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Model updated successfully.',
        ]);
    }

    public function deleteModel(int $modelId): void
    {
        $model = AiModel::find($modelId);
        if (! $model) {
            return;
        }

        $provider = $model->provider;
        if (! $provider || $provider->user_id !== $this->user->id) {
            return;
        }

        $model->delete();
        unset($this->providers);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Model deleted.',
        ]);
    }

    protected function resetModelForm(): void
    {
        $this->modelId = '';
        $this->modelDisplayName = '';
        $this->modelContextWindow = 128000;
        $this->modelSupportsVision = false;
        $this->modelSupportsStreaming = true;
    }

    protected function seedDefaultModels(Provider $provider): void
    {
        $models = match ($provider->name) {
            Provider::NAME_ANTHROPIC => [
                ['model_id' => 'claude-sonnet-4-20250514', 'display_name' => 'Claude Sonnet 4', 'context_window' => 200000, 'supports_vision' => true],
                ['model_id' => 'claude-3-7-sonnet-20250219', 'display_name' => 'Claude 3.7 Sonnet', 'context_window' => 200000, 'supports_vision' => true],
                ['model_id' => 'claude-3-5-sonnet-20241022', 'display_name' => 'Claude 3.5 Sonnet', 'context_window' => 200000, 'supports_vision' => true],
                ['model_id' => 'claude-3-5-haiku-20241022', 'display_name' => 'Claude 3.5 Haiku', 'context_window' => 200000, 'supports_vision' => true],
            ],
            Provider::NAME_OPENAI => [
                ['model_id' => 'gpt-4.1', 'display_name' => 'GPT-4.1', 'context_window' => 1047576, 'supports_vision' => true],
                ['model_id' => 'gpt-4.1-mini', 'display_name' => 'GPT-4.1 Mini', 'context_window' => 1047576, 'supports_vision' => true],
                ['model_id' => 'gpt-4o', 'display_name' => 'GPT-4o', 'context_window' => 128000, 'supports_vision' => true],
                ['model_id' => 'gpt-4o-mini', 'display_name' => 'GPT-4o Mini', 'context_window' => 128000, 'supports_vision' => true],
                ['model_id' => 'o3', 'display_name' => 'o3', 'context_window' => 200000, 'supports_vision' => true],
                ['model_id' => 'o3-mini', 'display_name' => 'o3 Mini', 'context_window' => 200000, 'supports_vision' => true],
            ],
            Provider::NAME_GEMINI => [
                ['model_id' => 'gemini-2.5-pro-preview-06-05', 'display_name' => 'Gemini 2.5 Pro', 'context_window' => 1048576, 'supports_vision' => true],
                ['model_id' => 'gemini-2.5-flash-preview-05-20', 'display_name' => 'Gemini 2.5 Flash', 'context_window' => 1048576, 'supports_vision' => true],
                ['model_id' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash', 'context_window' => 1048576, 'supports_vision' => true],
                ['model_id' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro', 'context_window' => 2097152, 'supports_vision' => true],
                ['model_id' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash', 'context_window' => 1048576, 'supports_vision' => true],
            ],
            Provider::NAME_OLLAMA, Provider::NAME_CLIPROXY => [],
            default => [],
        };

        foreach ($models as $model) {
            AiModel::updateOrCreate(
                [
                    'provider_id' => $provider->id,
                    'model_id' => $model['model_id'],
                ],
                [
                    'display_name' => $model['display_name'],
                    'context_window' => $model['context_window'],
                    'supports_vision' => $model['supports_vision'],
                    'supports_streaming' => true,
                    'is_available' => true,
                ]
            );
        }
    }
}; ?>

<x-layouts.app :title="__('Provider Settings')">
    @volt('settings.providers')
    <div>
        <div class="max-w-4xl mx-auto">
            <flux:heading size="xl">Provider Settings</flux:heading>
            <p class="mt-2 text-zinc-500">Configure your LLM providers and API keys</p>

            <flux:separator class="my-6" />

            {{-- Configured Providers --}}
            @if($this->providers->count() > 0)
                <div class="space-y-4">
                    <flux:heading size="lg">Configured Providers</flux:heading>

                    <div class="grid gap-4">
                        @foreach($this->providers as $provider)
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <x-provider-icon :provider="$provider->name" />
                                        <div>
                                            <flux:heading size="sm">{{ ucfirst($provider->name) }}</flux:heading>
                                            <p class="text-xs text-zinc-500">
                                                {{ $provider->models->count() }} models available
                                                @if($provider->masked_api_key)
                                                    • Key: {{ $provider->masked_api_key }}
                                                @endif
                                                @if($provider->base_url)
                                                    • {{ $provider->base_url }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        @if($provider->is_default)
                                            <flux:badge color="green" size="sm">Default</flux:badge>
                                        @else
                                            <flux:button wire:click="setDefault({{ $provider->id }})" size="sm" variant="ghost">
                                                Set Default
                                            </flux:button>
                                        @endif

                                        <flux:button wire:click="toggleActive({{ $provider->id }})" size="sm" variant="ghost">
                                            {{ $provider->is_active ? 'Disable' : 'Enable' }}
                                        </flux:button>

                                        @if(in_array($provider->name, [\App\Models\Provider::NAME_OLLAMA, \App\Models\Provider::NAME_CLIPROXY]))
                                            <flux:button wire:click="testConnection({{ $provider->id }})" size="sm" variant="ghost" title="Test Connection" wire:loading.attr="disabled" wire:target="testConnection">
                                                <flux:icon.signal class="size-4" wire:loading.class="animate-pulse" wire:target="testConnection({{ $provider->id }})" />
                                            </flux:button>
                                            <flux:button wire:click="syncModels({{ $provider->id }})" size="sm" variant="ghost" title="Sync Models" wire:loading.attr="disabled" wire:target="syncModels">
                                                <flux:icon.arrow-path class="size-4" wire:loading.class="animate-spin" wire:target="syncModels({{ $provider->id }})" />
                                            </flux:button>
                                        @else
                                            <flux:button wire:click="syncModels({{ $provider->id }})" size="sm" variant="ghost" title="Refresh Models">
                                                <flux:icon.arrow-path class="size-4" />
                                            </flux:button>
                                        @endif

                                        @if(in_array($provider->name, [\App\Models\Provider::NAME_CLIPROXY, \App\Models\Provider::NAME_OLLAMA]))
                                            <flux:button wire:click="openAddModelModal({{ $provider->id }})" size="sm" variant="ghost" title="Add Model">
                                                <flux:icon.plus class="size-4" />
                                            </flux:button>
                                        @endif

                                        <flux:button wire:click="openEditModal({{ $provider->id }})" size="sm" variant="ghost">
                                            <flux:icon.pencil class="size-4" />
                                        </flux:button>

                                        <flux:button wire:click="deleteProvider({{ $provider->id }})" size="sm" variant="ghost" class="text-red-500 hover:text-red-600">
                                            <flux:icon.trash class="size-4" />
                                        </flux:button>
                                    </div>
                                </div>

                                @if(! $provider->is_active)
                                    <flux:badge color="amber" size="sm" class="mt-2">Disabled</flux:badge>
                                @endif

                                {{-- Models List for CliproxyAPI and Ollama --}}
                                @if(in_array($provider->name, [\App\Models\Provider::NAME_CLIPROXY, \App\Models\Provider::NAME_OLLAMA]) && $provider->models->count() > 0)
                                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                        <p class="text-xs text-zinc-500 uppercase tracking-wider mb-2">Models</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            @foreach($provider->models as $model)
                                                <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800 rounded px-3 py-2 group">
                                                    <div>
                                                        <span class="text-sm font-medium">{{ $model->display_name }}</span>
                                                        <span class="text-xs text-zinc-400 ml-2">{{ $model->model_id }}</span>
                                                        @if($model->supports_vision)
                                                            <flux:badge size="sm" color="purple" class="ml-1">Vision</flux:badge>
                                                        @endif
                                                    </div>
                                                    <div class="hidden group-hover:flex items-center gap-1">
                                                        <button wire:click="openEditModelModal({{ $model->id }})" class="p-1 text-zinc-400 hover:text-zinc-600">
                                                            <flux:icon.pencil class="size-3" />
                                                        </button>
                                                        <button wire:click="deleteModel({{ $model->id }})" class="p-1 text-zinc-400 hover:text-red-500">
                                                            <flux:icon.trash class="size-3" />
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <flux:separator class="my-6" />
            @endif

            {{-- Add New Provider --}}
            @if(count($this->availableProviders) > 0)
                <div class="space-y-4">
                    <flux:heading size="lg">Add Provider</flux:heading>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($this->availableProviders as $name)
                            <button
                                wire:click="openAddModal('{{ $name }}')"
                                class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors text-left"
                            >
                                <div class="flex items-center gap-3">
                                    <x-provider-icon :provider="$name" />
                                    <flux:heading size="sm">{{ ucfirst($name) }}</flux:heading>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-zinc-500">All providers have been configured.</p>
            @endif
        </div>

        {{-- Add Provider Modal --}}
        <flux:modal wire:model="showAddModal" name="add-provider">
            <div class="space-y-6">
                <flux:heading size="lg">Add {{ ucfirst($providerName) }} Provider</flux:heading>

                <form wire:submit="addProvider" class="space-y-4">
                    @if(! in_array($providerName, [Provider::NAME_OLLAMA]))
                        <flux:input
                            wire:model="apiKey"
                            type="password"
                            label="API Key"
                            placeholder="Enter your API key"
                            required
                        />
                        @error('apiKey')
                            <p class="text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    @endif

                    @if(in_array($providerName, [Provider::NAME_OLLAMA, Provider::NAME_CLIPROXY]))
                        <flux:input
                            wire:model="baseUrl"
                            type="url"
                            label="Base URL"
                            :placeholder="$providerName === Provider::NAME_OLLAMA ? 'http://localhost:11434' : 'http://localhost:8317'"
                        />
                    @endif

                    {{-- Custom Headers for CliproxyAPI --}}
                    @if($providerName === Provider::NAME_CLIPROXY)
                        <div class="space-y-3">
                            <flux:heading size="sm">Custom Headers</flux:heading>
                            <p class="text-xs text-zinc-500">Add custom headers for authentication or other purposes</p>

                            @if(count($customHeaders) > 0)
                                <div class="space-y-2">
                                    @foreach($customHeaders as $key => $value)
                                        <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-800 rounded px-3 py-2">
                                            <span class="font-mono text-sm flex-1">{{ $key }}: {{ $value }}</span>
                                            <button type="button" wire:click="removeHeader('{{ $key }}')" class="text-red-500 hover:text-red-600">
                                                <flux:icon.x-mark class="size-4" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <flux:input wire:model="newHeaderKey" placeholder="Header name" class="flex-1" />
                                <flux:input wire:model="newHeaderValue" placeholder="Header value" class="flex-1" />
                                <flux:button type="button" wire:click="addHeader" variant="ghost">
                                    <flux:icon.plus class="size-4" />
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-3">
                        <flux:button wire:click="closeModals" type="button" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Add Provider</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Edit Provider Modal --}}
        <flux:modal wire:model="showEditModal" name="edit-provider">
            <div class="space-y-6">
                <flux:heading size="lg">Edit {{ ucfirst($providerName) }} Provider</flux:heading>

                <form wire:submit="updateProvider" class="space-y-4">
                    @if(! in_array($providerName, [Provider::NAME_OLLAMA]))
                        <flux:input
                            wire:model="apiKey"
                            type="password"
                            label="API Key"
                            placeholder="Leave blank to keep current key"
                        />
                    @endif

                    @if(in_array($providerName, [Provider::NAME_OLLAMA, Provider::NAME_CLIPROXY]))
                        <flux:input
                            wire:model="baseUrl"
                            type="url"
                            label="Base URL"
                            :placeholder="$providerName === Provider::NAME_OLLAMA ? 'http://localhost:11434' : 'http://localhost:8317'"
                        />
                    @endif

                    {{-- Custom Headers for CliproxyAPI --}}
                    @if($providerName === Provider::NAME_CLIPROXY)
                        <div class="space-y-3">
                            <flux:heading size="sm">Custom Headers</flux:heading>

                            @if(count($customHeaders) > 0)
                                <div class="space-y-2">
                                    @foreach($customHeaders as $key => $value)
                                        <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-800 rounded px-3 py-2">
                                            <span class="font-mono text-sm flex-1">{{ $key }}: {{ $value }}</span>
                                            <button type="button" wire:click="removeHeader('{{ $key }}')" class="text-red-500 hover:text-red-600">
                                                <flux:icon.x-mark class="size-4" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <flux:input wire:model="newHeaderKey" placeholder="Header name" class="flex-1" />
                                <flux:input wire:model="newHeaderValue" placeholder="Header value" class="flex-1" />
                                <flux:button type="button" wire:click="addHeader" variant="ghost">
                                    <flux:icon.plus class="size-4" />
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-3">
                        <flux:button wire:click="closeModals" type="button" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save Changes</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Add Model Modal --}}
        <flux:modal wire:model="showAddModelModal" name="add-model">
            <div class="space-y-6">
                <flux:heading size="lg">Add Custom Model</flux:heading>

                <form wire:submit="addModel" class="space-y-4">
                    <flux:input
                        wire:model="modelId"
                        label="Model ID"
                        placeholder="e.g., gpt-4o-mini, claude-3-sonnet"
                        required
                    />

                    <flux:input
                        wire:model="modelDisplayName"
                        label="Display Name"
                        placeholder="e.g., GPT-4o Mini"
                        required
                    />

                    <flux:input
                        wire:model="modelContextWindow"
                        type="number"
                        label="Context Window"
                        placeholder="128000"
                    />

                    <div class="flex gap-4">
                        <flux:checkbox wire:model="modelSupportsVision" label="Supports Vision" />
                        <flux:checkbox wire:model="modelSupportsStreaming" label="Supports Streaming" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button wire:click="closeModals" type="button" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Add Model</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        {{-- Edit Model Modal --}}
        <flux:modal wire:model="showEditModelModal" name="edit-model">
            <div class="space-y-6">
                <flux:heading size="lg">Edit Model</flux:heading>

                <form wire:submit="updateModel" class="space-y-4">
                    <flux:input
                        wire:model="modelId"
                        label="Model ID"
                        placeholder="e.g., gpt-4o-mini"
                        required
                    />

                    <flux:input
                        wire:model="modelDisplayName"
                        label="Display Name"
                        placeholder="e.g., GPT-4o Mini"
                        required
                    />

                    <flux:input
                        wire:model="modelContextWindow"
                        type="number"
                        label="Context Window"
                    />

                    <div class="flex gap-4">
                        <flux:checkbox wire:model="modelSupportsVision" label="Supports Vision" />
                        <flux:checkbox wire:model="modelSupportsStreaming" label="Supports Streaming" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <flux:button wire:click="closeModals" type="button" variant="ghost">Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Save Changes</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    </div>
    @endvolt
</x-layouts.app>
