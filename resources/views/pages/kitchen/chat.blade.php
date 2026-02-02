<?php

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('kitchen.chat');

new class extends Component
{
    #[Url]
    public ?int $conversation = null;

    public ?int $selectedModelId = null;

    public function mount(): void
    {
        $this->selectedModelId = $this->defaultModel?->id;
    }

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
            ->where('is_active', true)
            ->with('models')
            ->get();
    }

    #[Computed]
    public function defaultModel(): ?AiModel
    {
        $defaultProvider = Provider::query()
            ->where('user_id', $this->user->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($defaultProvider) {
            return $defaultProvider->models()->where('is_available', true)->first();
        }

        $firstProvider = $this->providers->first();

        return $firstProvider?->models()->where('is_available', true)->first();
    }

    #[Computed]
    public function currentConversation(): ?Conversation
    {
        if (! $this->conversation) {
            return null;
        }

        return Conversation::query()
            ->where('id', $this->conversation)
            ->where('user_id', $this->user->id)
            ->with('messages.aiModel.provider')
            ->first();
    }

    #[Computed]
    public function recentConversations()
    {
        return Conversation::query()
            ->where('user_id', $this->user->id)
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function archivedConversations()
    {
        return Conversation::query()
            ->where('user_id', $this->user->id)
            ->where('is_archived', true)
            ->orderByDesc('last_message_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function selectedModel(): ?AiModel
    {
        if (! $this->selectedModelId) {
            return null;
        }

        return AiModel::with('provider')->find($this->selectedModelId);
    }

    public function selectConversation(int $id): void
    {
        $this->conversation = $id;
    }

    public function newConversation(): void
    {
        $this->conversation = null;
    }

    #[On('stream-complete')]
    public function handleStreamComplete(int $conversationId): void
    {
        $this->conversation = $conversationId;
        unset($this->currentConversation);
        unset($this->recentConversations);
    }

    public function deleteConversation(int $id): void
    {
        $conversation = Conversation::query()
            ->where('id', $id)
            ->where('user_id', $this->user->id)
            ->first();

        if ($conversation) {
            $conversation->delete();
            if ($this->conversation === $id) {
                $this->conversation = null;
            }
            unset($this->recentConversations);
        }
    }

    public function archiveConversation(int $id): void
    {
        $conversation = Conversation::query()
            ->where('id', $id)
            ->where('user_id', $this->user->id)
            ->first();

        if ($conversation) {
            $conversation->update(['is_archived' => true]);
            if ($this->conversation === $id) {
                $this->conversation = null;
            }
            unset($this->recentConversations);
            unset($this->archivedConversations);
        }
    }
}; ?>

<x-layouts.app :title="__('Chat')">
    @volt('chat')
    <div
        class="flex h-[100dvh] -m-6"
        x-data="chatStream({
            conversationId: @js($this->conversation),
            modelId: @js($this->selectedModelId),
            csrfToken: @js(csrf_token()),
            streamUrl: @js(route('chat.stream'))
        })"
        x-on:model-changed.window="modelId = $event.detail.modelId"
        @keydown.meta.enter.window="sendMessage()"
        @keydown.ctrl.enter.window="sendMessage()"
        wire:ignore.self
    >
        {{-- Chat Sidebar --}}
        <div class="w-64 border-r border-zinc-200 dark:border-zinc-700 flex flex-col bg-zinc-50 dark:bg-zinc-900">
            {{-- New Chat Button --}}
            <div class="p-4">
                <flux:button wire:click="newConversation" x-on:click="resetChat()" icon="plus" variant="primary" class="w-full">
                    New Chat
                </flux:button>
            </div>

            {{-- Conversation List --}}
            <div class="flex-1 overflow-y-auto">
                @if($this->recentConversations->count() > 0)
                    <div class="px-4 py-2">
                        <span class="text-xs text-zinc-500 uppercase tracking-wider">Recent</span>
                    </div>
                    @foreach($this->recentConversations as $conv)
                        <div
                            wire:key="conv-{{ $conv->id }}"
                            class="group px-4 py-2 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 {{ $this->conversation === $conv->id ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}"
                            wire:click="selectConversation({{ $conv->id }})"
                            x-on:click="conversationId = {{ $conv->id }}; resetStreamState()"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm truncate flex-1 text-zinc-700 dark:text-zinc-300">
                                    {{ $conv->title ?? 'New conversation' }}
                                </span>
                                <div class="hidden group-hover:flex gap-1">
                                    <button
                                        wire:click.stop="archiveConversation({{ $conv->id }})"
                                        class="p-1 text-zinc-400 hover:text-zinc-600"
                                    >
                                        <flux:icon.archive-box class="size-3" />
                                    </button>
                                    <button
                                        wire:click.stop="deleteConversation({{ $conv->id }})"
                                        class="p-1 text-zinc-400 hover:text-red-600"
                                    >
                                        <flux:icon.trash class="size-3" />
                                    </button>
                                </div>
                            </div>
                            <span class="text-xs text-zinc-400">
                                {{ $conv->last_message_at?->diffForHumans() }}
                            </span>
                        </div>
                    @endforeach
                @endif

                @if($this->archivedConversations->count() > 0)
                    <div class="px-4 py-2 mt-4">
                        <span class="text-xs text-zinc-500 uppercase tracking-wider">Archived</span>
                    </div>
                    @foreach($this->archivedConversations as $conv)
                        <div
                            wire:key="archived-{{ $conv->id }}"
                            class="px-4 py-2 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 opacity-60"
                            wire:click="selectConversation({{ $conv->id }})"
                            x-on:click="conversationId = {{ $conv->id }}; resetStreamState()"
                        >
                            <span class="text-sm truncate text-zinc-700 dark:text-zinc-300">
                                {{ $conv->title ?? 'New conversation' }}
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Settings Link --}}
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                <a href="{{ route('kitchen.settings.providers') }}" class="flex items-center gap-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300" wire:navigate>
                    <flux:icon.cog-6-tooth class="size-4" />
                    <span class="text-sm">Provider Settings</span>
                </a>
            </div>
        </div>

        {{-- Main Chat Area --}}
        <div class="flex-1 flex flex-col bg-white dark:bg-zinc-800">
            {{-- Messages Container --}}
            <div
                class="flex-1 overflow-y-auto p-6"
                x-ref="messagesContainer"
                x-init="$watch('streamingContent', () => scrollToBottom())"
            >
                <template x-if="!conversationId && messages.length === 0 && !pendingUserMessage">
                    {{-- Welcome Screen --}}
                    <div class="flex-1 flex items-center justify-center h-full">
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-6 bg-black rounded-2xl flex items-center justify-center">
                                <flux:icon.chat-bubble-left-right class="size-8 text-white" />
                            </div>
                            <flux:heading size="xl">Hey! I'm FluxChat</flux:heading>
                            <p class="mt-2 text-zinc-500">Tell me everything you need</p>
                        </div>
                    </div>
                </template>

                <div class="max-w-3xl mx-auto space-y-6">
                    {{-- Server-side messages (existing conversation) --}}
                    @if($this->currentConversation)
                        @foreach($this->currentConversation->messages as $msg)
                            <div wire:key="msg-{{ $msg->id }}" class="flex {{ $msg->isUser() ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] {{ $msg->isUser() ? 'bg-blue-500 text-white' : 'bg-zinc-100 dark:bg-zinc-700' }} rounded-2xl px-4 py-3">
                                    @if($msg->isAssistant() && $msg->aiModel)
                                        <div class="flex items-center gap-2 mb-2">
                                            <flux:badge size="sm" color="zinc">
                                                {{ $msg->aiModel->display_name }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                    <div class="prose dark:prose-invert prose-sm max-w-none">
                                        {!! \Illuminate\Support\Str::markdown($msg->content) !!}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    {{-- Pending user message (optimistic UI) --}}
                    <div x-show="pendingUserMessage" x-cloak class="flex justify-end">
                        <div class="max-w-[80%] bg-blue-500 text-white rounded-2xl px-4 py-3">
                            <div class="prose prose-invert prose-sm max-w-none whitespace-pre-wrap" x-text="pendingUserMessage"></div>
                        </div>
                    </div>

                    {{-- Streaming assistant response --}}
                    <div x-show="isStreaming || (streamingContent && !streamCompleted)" x-cloak class="flex justify-start">
                        <div class="max-w-[80%] bg-zinc-100 dark:bg-zinc-700 rounded-2xl px-4 py-3">
                            {{-- Model badge --}}
                            <div x-show="streamingModel" class="flex items-center gap-2 mb-2">
                                <flux:badge size="sm" color="zinc">
                                    <span x-text="streamingModel"></span>
                                </flux:badge>
                            </div>

                            {{-- Streaming content or typing indicator --}}
                            <div class="prose dark:prose-invert prose-sm max-w-none">
                                <div x-show="streamingContent" x-html="renderMarkdown(streamingContent)"></div>
                                <div x-show="isStreaming && !streamingContent" class="flex items-center gap-1">
                                    <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce"></div>
                                    <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                    <div class="w-2 h-2 bg-zinc-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                </div>
                            </div>

                            {{-- Streaming cursor --}}
                            <span x-show="isStreaming && streamingContent" class="inline-block w-2 h-4 ml-0.5 bg-zinc-400 dark:bg-zinc-300 animate-pulse"></span>
                        </div>
                    </div>

                    {{-- Error message --}}
                    <div x-show="error" x-cloak class="flex justify-center">
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 rounded-xl px-4 py-3 flex items-center gap-3">
                            <flux:icon.exclamation-circle class="size-5 flex-shrink-0" />
                            <span x-text="error"></span>
                            <button @click="error = null; retryLastMessage()" class="text-sm underline hover:no-underline">
                                Retry
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Input Area --}}
            <div class="border-t border-zinc-200 dark:border-zinc-700 p-4">
                <div class="max-w-3xl mx-auto">
                    <form @submit.prevent="sendMessage()" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <flux:textarea
                                    x-model="message"
                                    placeholder="Ask anything... (Cmd/Ctrl+Enter to send)"
                                    rows="2"
                                    class="resize-none"
                                    x-bind:disabled="isStreaming"
                                    @keydown.enter.meta.prevent="sendMessage()"
                                    @keydown.enter.ctrl.prevent="sendMessage()"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 h-10 w-10 rounded-lg"
                                x-bind:disabled="isStreaming || !message.trim()"
                            >
                                <svg x-show="!isStreaming" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                </svg>
                                <svg x-show="isStreaming" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 animate-spin">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                x-show="isStreaming"
                                x-cloak
                                @click="abortStream()"
                                title="Stop generation"
                                class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 h-10 w-10 rounded-lg"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if($this->providers->count() > 0)
                                    <flux:select
                                        x-model="modelId"
                                        size="sm"
                                        x-bind:disabled="isStreaming"
                                    >
                                        @foreach($this->providers as $provider)
                                            <optgroup label="{{ ucfirst($provider->name) }}">
                                                @foreach($provider->models->where('is_available', true) as $model)
                                                    <option value="{{ $model->id }}">{{ $model->display_name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <span class="text-sm text-amber-500">
                                        <a href="{{ route('kitchen.settings.providers') }}" class="underline" wire:navigate>Configure a provider</a> to start chatting
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-4">
                                {{-- Token usage display --}}
                                <template x-if="usage">
                                    <span class="text-xs text-zinc-400">
                                        <span x-text="usage.input_tokens"></span> in / <span x-text="usage.output_tokens"></span> out tokens
                                    </span>
                                </template>

                                <span class="text-xs text-zinc-400">
                                    AI can make mistakes. Check important info.
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('chatStream', (config) => ({
                // Configuration
                conversationId: config.conversationId,
                modelId: config.modelId,
                csrfToken: config.csrfToken,
                streamUrl: config.streamUrl,

                // State
                message: '',
                messages: [],
                isStreaming: false,
                streamingContent: '',
                streamingModel: null,
                streamCompleted: false,
                pendingUserMessage: null,
                error: null,
                usage: null,
                abortController: null,
                lastMessage: null,

                init() {
                    // Model selection is now handled purely through Alpine x-model
                    // No need to watch $wire.selectedModelId
                },

                resetChat() {
                    this.conversationId = null;
                    this.message = '';
                    this.messages = [];
                    this.resetStreamState();
                },

                resetStreamState() {
                    this.streamingContent = '';
                    this.streamingModel = null;
                    this.streamCompleted = false;
                    this.pendingUserMessage = null;
                    this.error = null;
                    this.usage = null;
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        if (this.$refs.messagesContainer) {
                            this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight;
                        }
                    });
                },

                renderMarkdown(content) {
                    // Basic markdown rendering - in production, use a proper library
                    // For now, just handle basic formatting and preserve newlines
                    if (!content) return '';

                    return content
                        // Escape HTML
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        // Code blocks (triple backticks)
                        .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre class="bg-zinc-200 dark:bg-zinc-600 p-3 rounded-lg overflow-x-auto my-2"><code>$2</code></pre>')
                        // Inline code
                        .replace(/`([^`]+)`/g, '<code class="bg-zinc-200 dark:bg-zinc-600 px-1 py-0.5 rounded">$1</code>')
                        // Bold
                        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                        // Italic
                        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                        // Line breaks
                        .replace(/\n/g, '<br>');
                },

                async sendMessage() {
                    const trimmedMessage = this.message.trim();
                    if (!trimmedMessage || this.isStreaming) return;

                    if (!this.modelId) {
                        this.error = 'Please select a model first.';
                        return;
                    }

                    // Store for retry functionality
                    this.lastMessage = trimmedMessage;

                    // Optimistic UI update
                    this.pendingUserMessage = trimmedMessage;
                    this.message = '';
                    this.error = null;
                    this.usage = null;
                    this.isStreaming = true;
                    this.streamingContent = '';
                    this.streamingModel = null;

                    // Scroll to show new message
                    this.scrollToBottom();

                    // Create abort controller for cancellation
                    this.abortController = new AbortController();

                    try {
                        const response = await fetch(this.streamUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'text/event-stream',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify({
                                conversation_id: this.conversationId,
                                model_id: parseInt(this.modelId),
                                message: trimmedMessage,
                            }),
                            signal: this.abortController.signal,
                        });

                        if (!response.ok) {
                            const errorData = await response.json().catch(() => ({}));
                            throw new Error(errorData.message || `Server error: ${response.status}`);
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';

                        while (true) {
                            const { done, value } = await reader.read();

                            if (done) break;

                            buffer += decoder.decode(value, { stream: true });

                            // Process complete SSE events
                            const lines = buffer.split('\n');
                            buffer = lines.pop() || ''; // Keep incomplete line in buffer

                            for (const line of lines) {
                                if (line.startsWith('event:')) {
                                    continue; // Event type line, data follows
                                }

                                if (line.startsWith('data:')) {
                                    const jsonStr = line.slice(5).trim();
                                    if (!jsonStr) continue;

                                    try {
                                        const data = JSON.parse(jsonStr);
                                        this.handleStreamEvent(data);
                                    } catch (e) {
                                        console.error('Failed to parse SSE data:', jsonStr, e);
                                    }
                                }
                            }
                        }
                    } catch (err) {
                        if (err.name === 'AbortError') {
                            // User cancelled - keep partial content
                            console.log('Stream aborted by user');
                        } else {
                            console.error('Stream error:', err);
                            this.error = err.message || 'Failed to send message. Please try again.';
                        }
                    } finally {
                        this.isStreaming = false;
                        this.abortController = null;
                    }
                },

                handleStreamEvent(data) {
                    switch (data.type) {
                        case 'start':
                            this.conversationId = data.conversation_id;
                            this.streamingModel = data.model || null;
                            break;

                        case 'delta':
                            this.streamingContent += data.content || '';
                            this.scrollToBottom();
                            break;

                        case 'end':
                            if (data.conversation_id) {
                                this.conversationId = data.conversation_id;
                            }

                            if (data.usage) {
                                this.usage = data.usage;
                            }

                            this.isStreaming = false;
                            this.streamCompleted = true;
                            this.pendingUserMessage = null;

                            this.$wire.dispatch('stream-complete', { conversationId: this.conversationId });

                            setTimeout(() => {
                                this.streamingContent = '';
                                this.streamingModel = null;
                                this.streamCompleted = false;
                            }, 500);
                            break;

                        case 'error':
                            this.error = data.message || 'An error occurred during streaming.';
                            this.isStreaming = false;
                            break;
                    }
                },

                abortStream() {
                    if (this.abortController) {
                        this.abortController.abort();
                    }
                },

                retryLastMessage() {
                    if (this.lastMessage) {
                        this.message = this.lastMessage;
                        this.error = null;
                        this.pendingUserMessage = null;
                        this.streamingContent = '';
                        this.sendMessage();
                    }
                },
            }));
        });
    </script>
    @endpush
    @endvolt
</x-layouts.app>
