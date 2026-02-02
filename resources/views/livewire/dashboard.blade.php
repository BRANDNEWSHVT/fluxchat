<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    #[Livewire\Attributes\Computed]
    public function user()
    {
        return Auth::user();
    }

    #[Livewire\Attributes\Computed]
    public function stats()
    {
        $userId = $this->user?->id;

        if (! $userId) {
            return $this->emptyStats();
        }

        $conversationsCount = Conversation::where('user_id', $userId)->count();
        $messagesCount = Message::whereHas('conversation', fn ($q) => $q->where('user_id', $userId))->count();
        $providersCount = Provider::where('user_id', $userId)->where('is_active', true)->count();
        $tokensUsed = Message::whereHas('conversation', fn ($q) => $q->where('user_id', $userId))->sum('token_count');

        return [
            [
                'title' => 'Total Conversations',
                'value' => number_format($conversationsCount),
                'icon' => 'chat-bubble-left-right',
                'bgClass' => 'bg-blue-100 dark:bg-blue-900/30',
                'iconClass' => 'text-blue-600 dark:text-blue-400',
            ],
            [
                'title' => 'Messages Sent',
                'value' => number_format($messagesCount),
                'icon' => 'chat-bubble-bottom-center-text',
                'bgClass' => 'bg-purple-100 dark:bg-purple-900/30',
                'iconClass' => 'text-purple-600 dark:text-purple-400',
            ],
            [
                'title' => 'Active Providers',
                'value' => $providersCount,
                'icon' => 'server-stack',
                'bgClass' => 'bg-green-100 dark:bg-green-900/30',
                'iconClass' => 'text-green-600 dark:text-green-400',
            ],
            [
                'title' => 'Tokens Used',
                'value' => $this->formatTokens($tokensUsed),
                'icon' => 'calculator',
                'bgClass' => 'bg-amber-100 dark:bg-amber-900/30',
                'iconClass' => 'text-amber-600 dark:text-amber-400',
            ],
        ];
    }

    #[Livewire\Attributes\Computed]
    public function recentConversations()
    {
        if (! $this->user) {
            return collect();
        }

        return Conversation::query()
            ->where('user_id', $this->user->id)
            ->where('is_archived', false)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();
    }

    #[Livewire\Attributes\Computed]
    public function providers()
    {
        if (! $this->user) {
            return collect();
        }

        return Provider::query()
            ->where('user_id', $this->user->id)
            ->withCount('models')
            ->orderBy('name')
            ->get();
    }

    protected function emptyStats(): array
    {
        return [
            ['title' => 'Total Conversations', 'value' => '0', 'icon' => 'chat-bubble-left-right', 'bgClass' => 'bg-blue-100 dark:bg-blue-900/30', 'iconClass' => 'text-blue-600 dark:text-blue-400'],
            ['title' => 'Messages Sent', 'value' => '0', 'icon' => 'chat-bubble-bottom-center-text', 'bgClass' => 'bg-purple-100 dark:bg-purple-900/30', 'iconClass' => 'text-purple-600 dark:text-purple-400'],
            ['title' => 'Active Providers', 'value' => '0', 'icon' => 'server-stack', 'bgClass' => 'bg-green-100 dark:bg-green-900/30', 'iconClass' => 'text-green-600 dark:text-green-400'],
            ['title' => 'Tokens Used', 'value' => '0', 'icon' => 'calculator', 'bgClass' => 'bg-amber-100 dark:bg-amber-900/30', 'iconClass' => 'text-amber-600 dark:text-amber-400'],
        ];
    }

    protected function formatTokens(int $tokens): string
    {
        if ($tokens >= 1000000) {
            return round($tokens / 1000000, 1).'M';
        }
        if ($tokens >= 1000) {
            return round($tokens / 1000, 1).'K';
        }

        return (string) $tokens;
    }
};

?>

<div>
    {{-- Welcome Header --}}
    <div class="mb-8">
        <flux:heading size="xl" level="1">
            Welcome back{{ $this->user ? ', ' . $this->user->name : '' }}!
        </flux:heading>
        <p class="mt-2 text-zinc-500">Here's an overview of your AI chat activity</p>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        @foreach ($this->stats as $stat)
            <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-6 border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg {{ $stat['bgClass'] }} flex items-center justify-center">
                        <flux:icon :name="$stat['icon']" class="size-5 {{ $stat['iconClass'] }}" />
                    </div>
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $stat['title'] }}</p>
                <flux:heading size="xl">{{ $stat['value'] }}</flux:heading>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Conversations --}}
        <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Recent Conversations</flux:heading>
                    <flux:button href="{{ route('kitchen.chat') }}" variant="ghost" size="sm" wire:navigate>
                        View all
                        <flux:icon.arrow-right class="size-4 ml-1" />
                    </flux:button>
                </div>
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->recentConversations as $conversation)
                    <a
                        href="{{ route('kitchen.chat') }}?conversation={{ $conversation->id }}"
                        class="flex items-center gap-4 p-4 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                        wire:navigate
                    >
                        <div class="w-10 h-10 rounded-full bg-black flex items-center justify-center text-white">
                            <flux:icon.chat-bubble-left class="size-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $conversation->title ?? 'New conversation' }}
                            </p>
                            <p class="text-sm text-zinc-500 truncate">
                                {{ $conversation->messages->first()?->content ?? 'No messages yet' }}
                            </p>
                        </div>
                        <span class="text-xs text-zinc-400">
                            {{ $conversation->last_message_at?->diffForHumans(short: true) }}
                        </span>
                    </a>
                @empty
                    <div class="p-8 text-center">
                        <flux:icon.chat-bubble-left-right class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" />
                        <p class="text-zinc-500">No conversations yet</p>
                        <flux:button href="{{ route('kitchen.chat') }}" variant="primary" size="sm" class="mt-4" wire:navigate>
                            Start chatting
                        </flux:button>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Configured Providers --}}
        <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Your Providers</flux:heading>
                    <flux:button href="{{ route('kitchen.settings.providers') }}" variant="ghost" size="sm" wire:navigate>
                        Manage
                        <flux:icon.arrow-right class="size-4 ml-1" />
                    </flux:button>
                </div>
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->providers as $provider)
                    <div class="flex items-center gap-4 p-4">
                        <x-provider-icon :provider="$provider->name" />
                        <div class="flex-1">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ ucfirst($provider->name) }}
                            </p>
                            <p class="text-sm text-zinc-500">
                                {{ $provider->models_count }} models available
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($provider->is_default)
                                <flux:badge color="green" size="sm">Default</flux:badge>
                            @endif
                            @if($provider->is_active)
                                <flux:badge color="blue" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <flux:icon.server-stack class="size-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-4" />
                        <p class="text-zinc-500">No providers configured</p>
                        <flux:button href="{{ route('kitchen.settings.providers') }}" variant="primary" size="sm" class="mt-4" wire:navigate>
                            Add provider
                        </flux:button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        <a
            href="{{ route('kitchen.chat') }}"
            class="flex items-center gap-4 p-4 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl text-white hover:from-blue-600 hover:to-blue-700 transition-colors"
            wire:navigate
        >
            <flux:icon.plus-circle class="size-8" />
            <div>
                <p class="font-semibold">New Chat</p>
                <p class="text-sm text-blue-100">Start a new conversation</p>
            </div>
        </a>

        <a
            href="{{ route('kitchen.settings.providers') }}"
            class="flex items-center gap-4 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
            wire:navigate
        >
            <flux:icon.cog-6-tooth class="size-8 text-zinc-500" />
            <div>
                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Settings</p>
                <p class="text-sm text-zinc-500">Configure providers</p>
            </div>
        </a>

        <a
            href="{{ route('kitchen.settings.profile') }}"
            class="flex items-center gap-4 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-xl hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
            wire:navigate
        >
            <flux:icon.user-circle class="size-8 text-zinc-500" />
            <div>
                <p class="font-semibold text-zinc-900 dark:text-zinc-100">Profile</p>
                <p class="text-sm text-zinc-500">Manage your account</p>
            </div>
        </a>
    </div>
</div>
