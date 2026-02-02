<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ConversationService
{
    public function createConversation(User $user, ?string $title = null): Conversation
    {
        return Conversation::create([
            'user_id' => $user->id,
            'title' => $title,
            'last_message_at' => now(),
        ]);
    }

    public function addUserMessage(Conversation $conversation, string $content): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => $content,
        ]);

        $conversation->updateLastMessageAt();

        return $message;
    }

    public function addAssistantMessage(
        Conversation $conversation,
        string $content,
        AiModel $model,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?array $metadata = null
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'ai_model_id' => $model->id,
            'role' => Message::ROLE_ASSISTANT,
            'content' => $content,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'metadata' => $metadata,
        ]);

        $conversation->updateLastMessageAt();

        $this->maybeGenerateTitle($conversation);

        return $message;
    }

    public function updateTitle(Conversation $conversation, string $title): Conversation
    {
        $conversation->update(['title' => $title]);

        return $conversation;
    }

    public function archive(Conversation $conversation): Conversation
    {
        $conversation->update(['is_archived' => true]);

        return $conversation;
    }

    public function unarchive(Conversation $conversation): Conversation
    {
        $conversation->update(['is_archived' => false]);

        return $conversation;
    }

    public function delete(Conversation $conversation): bool
    {
        return $conversation->delete();
    }

    public function restore(Conversation $conversation): bool
    {
        return $conversation->restore();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getRecentConversations(User $user, int $limit = 50): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getArchivedConversations(User $user, int $limit = 50): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->where('is_archived', true)
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function search(User $user, string $query): Collection
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhereHas('messages', function ($mq) use ($query) {
                        $mq->where('content', 'like', "%{$query}%");
                    });
            })
            ->orderByDesc('last_message_at')
            ->get();
    }

    private function maybeGenerateTitle(Conversation $conversation): void
    {
        if ($conversation->title !== null) {
            return;
        }

        $messageCount = $conversation->messages()->count();
        if ($messageCount < 2) {
            return;
        }

        $firstMessage = $conversation->messages()->where('role', Message::ROLE_USER)->first();
        if (! $firstMessage) {
            return;
        }

        $title = mb_substr($firstMessage->content, 0, 50);
        if (mb_strlen($firstMessage->content) > 50) {
            $title .= '...';
        }

        $conversation->update(['title' => $title]);
    }
}
