<?php

declare(strict_types=1);

namespace App\Services\LLM\Cliproxy\Handlers;

use App\Services\LLM\Cliproxy\Cliproxy;
use Generator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Concerns\CallsTools;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismStreamDecodeException;
use Prism\Prism\Providers\OpenRouter\Maps\MessageMap;
use Prism\Prism\Streaming\EventID;
use Prism\Prism\Streaming\Events\StepFinishEvent;
use Prism\Prism\Streaming\Events\StepStartEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\StreamStartEvent;
use Prism\Prism\Streaming\Events\TextCompleteEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\TextStartEvent;
use Prism\Prism\Streaming\StreamState;
use Prism\Prism\Text\Request;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\Usage;
use Psr\Http\Message\StreamInterface;
use Throwable;

final class Stream
{
    use CallsTools;

    protected StreamState $state;

    public function __construct(protected Cliproxy $provider)
    {
        $this->state = new StreamState();
    }

    /**
     * @return Generator<StreamEvent>
     */
    public function handle(Request $request): Generator
    {
        $response = $this->sendRequest($request);

        yield from $this->processStream($response, $request);
    }

    /**
     * @return Generator<StreamEvent>
     */
    protected function processStream(
        Response $response,
        Request $request,
        int $depth = 0,
    ): Generator {
        if ($depth === 0) {
            $this->state->reset();
        }

        $text = '';
        $toolCalls = [];

        while (! $response->getBody()->eof()) {
            $data = $this->parseNextDataLine($response->getBody());

            if ($data === null) {
                continue;
            }

            if ($this->state->shouldEmitStreamStart()) {
                $this->state->withMessageId(EventID::generate('msg'))->markStreamStarted();

                yield new StreamStartEvent(
                    id: EventID::generate(),
                    timestamp: time(),
                    model: $data['model'] ?? $request->model(),
                    provider: 'cliproxy',
                );
            }

            if ($this->state->shouldEmitStepStart()) {
                $this->state->markStepStarted();

                yield new StepStartEvent(id: EventID::generate(), timestamp: time());
            }

            if ($this->hasToolCalls($data)) {
                $toolCalls = $this->extractToolCalls($data, $toolCalls);

                $finishReason = data_get($data, 'choices.0.finish_reason');
                if (
                    $finishReason !== null &&
                    $this->mapFinishReason($data) === FinishReason::ToolCalls
                ) {
                    if ($this->state->hasTextStarted() && $text !== '') {
                        $this->state->markTextCompleted();

                        yield new TextCompleteEvent(
                            id: EventID::generate(),
                            timestamp: time(),
                            messageId: $this->state->messageId(),
                        );
                    }

                    yield from $this->handleToolCalls($request, $text, $toolCalls, $depth);

                    return;
                }

                continue;
            }

            $finishReasonValue = data_get($data, 'choices.0.finish_reason');
            if (
                $finishReasonValue !== null &&
                $this->mapFinishReason($data) === FinishReason::ToolCalls
            ) {
                if ($this->state->hasTextStarted() && $text !== '') {
                    $this->state->markTextCompleted();

                    yield new TextCompleteEvent(
                        id: EventID::generate(),
                        timestamp: time(),
                        messageId: $this->state->messageId(),
                    );
                }

                yield from $this->handleToolCalls($request, $text, $toolCalls, $depth);

                return;
            }

            $content = $this->extractContentDelta($data);
            if ($content !== '') {
                if ($this->state->shouldEmitTextStart()) {
                    $this->state->markTextStarted();

                    yield new TextStartEvent(
                        id: EventID::generate(),
                        timestamp: time(),
                        messageId: $this->state->messageId(),
                    );
                }

                $text .= $content;

                yield new TextDeltaEvent(
                    id: EventID::generate(),
                    timestamp: time(),
                    delta: $content,
                    messageId: $this->state->messageId(),
                );
            }

            $rawFinishReason = data_get($data, 'choices.0.finish_reason');
            if ($rawFinishReason !== null) {
                $finishReason = $this->mapFinishReason($data);

                if ($this->state->hasTextStarted() && $text !== '') {
                    $this->state->markTextCompleted();

                    yield new TextCompleteEvent(
                        id: EventID::generate(),
                        timestamp: time(),
                        messageId: $this->state->messageId(),
                    );
                }

                $this->state->withFinishReason($finishReason);
            }

            $usage = $this->extractUsage($data);
            if ($usage instanceof Usage) {
                $this->state->addUsage($usage);
            }
        }

        $this->state->markStepFinished();
        yield new StepFinishEvent(id: EventID::generate(), timestamp: time());

        yield $this->emitStreamEndEvent();
    }

    protected function emitStreamEndEvent(): StreamEndEvent
    {
        return new StreamEndEvent(
            id: EventID::generate(),
            timestamp: time(),
            finishReason: $this->state->finishReason() ?? FinishReason::Stop,
            usage: $this->state->usage(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseNextDataLine(StreamInterface $stream): ?array
    {
        $line = $this->readLine($stream);

        if (! str_starts_with($line, 'data:')) {
            return null;
        }

        $line = mb_trim(mb_substr($line, mb_strlen('data: ')));

        if (Str::contains($line, '[DONE]')) {
            return null;
        }

        try {
            return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new PrismStreamDecodeException('Cliproxy', $e);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hasToolCalls(array $data): bool
    {
        return ! empty($data['choices'][0]['delta']['tool_calls']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    protected function extractToolCalls(array $data, array $toolCalls): array
    {
        $deltaToolCalls = data_get($data, 'choices.0.delta.tool_calls', []);

        foreach ($deltaToolCalls as $deltaToolCall) {
            $index = $deltaToolCall['index'];

            if (isset($deltaToolCall['id'])) {
                $toolCalls[$index]['id'] = $deltaToolCall['id'];
            }

            if (isset($deltaToolCall['type'])) {
                $toolCalls[$index]['type'] = $deltaToolCall['type'];
            }

            if (isset($deltaToolCall['function'])) {
                if (isset($deltaToolCall['function']['name'])) {
                    $toolCalls[$index]['function']['name'] = $deltaToolCall['function']['name'];
                }

                if (isset($deltaToolCall['function']['arguments'])) {
                    $toolCalls[$index]['function']['arguments'] =
                        ($toolCalls[$index]['function']['arguments'] ?? '').
                        $deltaToolCall['function']['arguments'];
                }
            }
        }

        return $toolCalls;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractContentDelta(array $data): string
    {
        return data_get($data, 'choices.0.delta.content', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapFinishReason(array $data): FinishReason
    {
        $finishReason = data_get($data, 'choices.0.finish_reason');

        return match ($finishReason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::Length,
            'tool_calls', 'function_call' => FinishReason::ToolCalls,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return Generator<StreamEvent>
     */
    protected function handleToolCalls(
        Request $request,
        string $text,
        array $toolCalls,
        int $depth,
    ): Generator {
        $mappedToolCalls = $this->mapToolCalls($toolCalls);

        $toolResults = [];
        yield from $this->callToolsAndYieldEvents(
            $request->tools(),
            $mappedToolCalls,
            $this->state->messageId(),
            $toolResults,
        );

        $request->addMessage(new AssistantMessage($text, $mappedToolCalls));
        $request->addMessage(new ToolResultMessage($toolResults));
        $request->resetToolChoice();

        $this->state->resetTextState()->withMessageId(EventID::generate('msg'));

        $depth++;

        $this->state->markStepFinished();
        yield new StepFinishEvent(id: EventID::generate(), timestamp: time());

        if ($depth < $request->maxSteps()) {
            $nextResponse = $this->sendRequest($request);
            yield from $this->processStream($nextResponse, $request, $depth);
        } else {
            yield $this->emitStreamEndEvent();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, ToolCall>
     */
    protected function mapToolCalls(array $toolCalls): array
    {
        return collect($toolCalls)
            ->map(function ($toolCall): ToolCall {
                $arguments = data_get($toolCall, 'function.arguments', '');

                if (is_string($arguments) && $arguments !== '') {
                    try {
                        $parsedArguments = json_decode(
                            $arguments,
                            true,
                            flags: JSON_THROW_ON_ERROR,
                        );
                        $arguments = $parsedArguments;
                    } catch (Throwable) {
                        $arguments = ['raw' => $arguments];
                    }
                }

                return new ToolCall(
                    data_get($toolCall, 'id'),
                    data_get($toolCall, 'function.name'),
                    $arguments,
                );
            })
            ->all();
    }

    protected function sendRequest(Request $request): Response
    {
        $options = [];

        if ($request->temperature() !== null) {
            $options['temperature'] = $request->temperature();
        }

        if ($request->topP() !== null) {
            $options['top_p'] = $request->topP();
        }

        $payload = array_merge(
            [
                'stream' => true,
                'model' => $request->model(),
                'messages' => new MessageMap($request->messages(), $request->systemPrompts())(),
                'max_tokens' => $request->maxTokens(),
                'stream_options' => ['include_usage' => true],
            ],
            $options,
        );

        Log::info('Cliproxy Stream Request', [
            'url' => $this->provider->url,
            'payload' => $payload,
        ]);

        try {
            /** @var Response $response */
            $response = $this->provider
                ->client()
                ->withOptions(['stream' => true])
                ->post('chat/completions', $payload);

            if ($response->failed()) {
                Log::error('Cliproxy Stream Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (Throwable $e) {
            Log::error('Cliproxy Stream Request Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function readLine(StreamInterface $stream): string
    {
        $buffer = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);

            if ($byte === '') {
                return $buffer;
            }

            $buffer .= $byte;

            if ($byte === "\n") {
                break;
            }
        }

        return $buffer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractUsage(array $data): ?Usage
    {
        $usage = data_get($data, 'usage');

        if (! $usage) {
            return null;
        }

        return new Usage(
            promptTokens: (int) data_get($usage, 'prompt_tokens', 0),
            completionTokens: (int) data_get($usage, 'completion_tokens', 0),
        );
    }
}
