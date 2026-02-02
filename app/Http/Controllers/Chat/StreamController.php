<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\LLM\LLMService;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamController extends Controller
{
    public function __construct(
        protected LLMService $llmService,
        protected ConversationService $conversationService
    ) {}

    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'model_id' => 'required|integer|exists:ai_models,id',
            'message' => 'required|string|max:32000',
            'system_prompt' => 'nullable|string|max:8000',
        ]);

        $user = $request->user();
        $model = AiModel::with('provider')->findOrFail($request->model_id);

        if ($model->provider->user_id !== $user->id) {
            abort(403, 'You do not have access to this model.');
        }

        $conversation = $request->conversation_id
            ? Conversation::where('id', $request->conversation_id)->where('user_id', $user->id)->firstOrFail()
            : $this->conversationService->createConversation($user);

        $this->conversationService->addUserMessage($conversation, $request->message);

        return new StreamedResponse(function () use ($model, $conversation, $request) {
            $fullContent = '';
            $inputTokens = null;
            $outputTokens = null;

            echo "event: start\n";
            echo 'data: '.json_encode([
                'type' => 'start',
                'conversation_id' => $conversation->id,
                'model' => $model->display_name,
            ])."\n\n";
            ob_flush();
            flush();

            try {
                $stream = $this->llmService->streamMessage(
                    $model->provider,
                    $model,
                    $conversation,
                    $request->message,
                    $request->system_prompt
                );

                foreach ($stream as $chunk) {
                    if ($chunk['type'] === 'delta') {
                        $fullContent .= $chunk['content'];
                        echo "event: delta\n";
                        echo 'data: '.json_encode([
                            'type' => 'delta',
                            'content' => $chunk['content'],
                        ])."\n\n";
                        ob_flush();
                        flush();
                    } elseif ($chunk['type'] === 'end') {
                        $inputTokens = $chunk['usage']['input_tokens'] ?? null;
                        $outputTokens = $chunk['usage']['output_tokens'] ?? null;
                    }
                }

                $this->conversationService->addAssistantMessage(
                    $conversation,
                    $fullContent,
                    $model,
                    $inputTokens,
                    $outputTokens
                );

                echo "event: end\n";
                echo 'data: '.json_encode([
                    'type' => 'end',
                    'conversation_id' => $conversation->id,
                    'usage' => [
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                    ],
                ])."\n\n";
                ob_flush();
                flush();

            } catch (Exception $e) {
                echo "event: error\n";
                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ])."\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
