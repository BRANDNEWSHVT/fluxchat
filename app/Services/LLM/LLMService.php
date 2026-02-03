<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Provider;
use Generator;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prism\Prism\Enums\Provider as PrismProvider;
use Prism\Prism\Enums\StreamEventType;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

final class LLMService
{
    public function sendMessage(
        Provider $provider,
        AiModel $model,
        Conversation $conversation,
        string $userMessage,
        ?string $systemPrompt = null,
    ): Message {
        $messages = $this->buildMessageHistory($conversation);

        $prismProvider = $this->mapProvider($provider->name);

        // Build provider config first
        $providerConfig = [];
        if ($provider->api_key) {
            $providerConfig['api_key'] = $provider->api_key;
        }
        if ($provider->base_url) {
            $providerConfig['url'] = $provider->base_url;
        }

        // Log provider config for debugging
        Log::debug('LLMService sendMessage provider config : ', $providerConfig);

        // Pass config directly to using() to avoid double initialization
        $prism = Prism::text()->using($prismProvider, $model->model_id, $providerConfig);

        if ($systemPrompt) {
            $prism->withSystemPrompt($systemPrompt);
        }

        // Add the new user message to the history
        $messages[] = new UserMessage($userMessage);

        // Use withMessages for the full conversation history
        $prism->withMessages($messages);

        $response = $prism->asText();

        return Message::create([
            'conversation_id' => $conversation->id,
            'ai_model_id' => $model->id,
            'role' => Message::ROLE_ASSISTANT,
            'content' => $response->text,
            'input_tokens' => $response->usage->promptTokens ?? null,
            'output_tokens' => $response->usage->completionTokens ?? null,
            'metadata' => [
                'finish_reason' => $response->finishReason?->name ?? null,
                'response_id' => $response->responseMessages[0]->id ?? null,
            ],
        ]);
    }

    public function streamMessage(
        Provider $provider,
        AiModel $model,
        Conversation $conversation,
        string $userMessage,
        ?string $systemPrompt = null,
    ): Generator {
        $messages = $this->buildMessageHistory($conversation);

        $prismProvider = $this->mapProvider($provider->name);

        // Build provider config first
        $providerConfig = [];
        if ($provider->api_key) {
            $providerConfig['api_key'] = $provider->api_key;
        }
        if ($provider->base_url) {
            $providerConfig['url'] = $provider->base_url;
        }

        // Pass config directly to using() to avoid double initialization
        $prism = Prism::text()->using($prismProvider, $model->model_id, $providerConfig);

        if ($systemPrompt) {
            $prism->withSystemPrompt($systemPrompt);
        }

        // Add the new user message to the history
        $messages[] = new UserMessage($userMessage);

        // Use withMessages for the full conversation history
        $prism->withMessages($messages);

        $stream = $prism->asStream();

        $fullContent = '';
        $inputTokens = null;
        $outputTokens = null;

        foreach ($stream as $event) {
            $eventType = $event->type();

            if ($eventType === StreamEventType::TextDelta) {
                $fullContent .= $event->delta;
                yield [
                    'type' => 'delta',
                    'content' => $event->delta,
                ];
            } elseif ($eventType === StreamEventType::StreamEnd) {
                if ($event->usage) {
                    $inputTokens = $event->usage->promptTokens;
                    $outputTokens = $event->usage->completionTokens;
                }
            }
        }

        yield [
            'type' => 'end',
            'content' => $fullContent,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ],
        ];
    }

    /**
     * @return array<UserMessage|AssistantMessage>
     */
    private function buildMessageHistory(Conversation $conversation): array
    {
        $prismMessages = [];

        foreach ($conversation->messages()->orderBy('created_at')->get() as $message) {
            if ($message->role === Message::ROLE_USER) {
                $prismMessages[] = new UserMessage($message->content);
            } elseif ($message->role === Message::ROLE_ASSISTANT) {
                $prismMessages[] = new AssistantMessage($message->content);
            }
        }

        return $prismMessages;
    }

    private function mapProvider(string $name): PrismProvider|string
    {
        return match ($name) {
            Provider::NAME_ANTHROPIC => PrismProvider::Anthropic,
            Provider::NAME_OPENAI => PrismProvider::OpenAI,
            Provider::NAME_GEMINI => PrismProvider::Gemini,
            Provider::NAME_OLLAMA => PrismProvider::Ollama,
            Provider::NAME_CLIPROXY => 'cliproxy',
            default => throw new InvalidArgumentException("Unknown provider: {$name}"),
        };
    }
}
