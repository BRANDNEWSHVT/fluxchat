<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Provider;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(ConversationService::class);
});

test('can create conversation', function () {
    $conversation = $this->service->createConversation($this->user);

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->last_message_at)->not->toBeNull();
});

test('can create conversation with title', function () {
    $title = 'My test conversation';
    $conversation = $this->service->createConversation($this->user, $title);

    expect($conversation->title)->toBe($title);
});

test('can add user message', function () {
    $conversation = Conversation::factory()->for($this->user)->create();
    $content = 'Hello, this is a test message';

    $message = $this->service->addUserMessage($conversation, $content);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role)->toBe(Message::ROLE_USER)
        ->and($message->content)->toBe($content)
        ->and($message->conversation_id)->toBe($conversation->id);
});

test('adding user message updates last_message_at', function () {
    $conversation = Conversation::factory()->for($this->user)->create([
        'last_message_at' => now()->subHour(),
    ]);

    $oldTime = $conversation->last_message_at;
    $this->service->addUserMessage($conversation, 'Test');

    expect($conversation->fresh()->last_message_at)->toBeGreaterThan($oldTime);
});

test('can add assistant message', function () {
    $provider = Provider::factory()->for($this->user)->anthropic()->create();
    $model = AiModel::factory()->for($provider)->claude35Sonnet()->create();
    $conversation = Conversation::factory()->for($this->user)->create();

    $message = $this->service->addAssistantMessage(
        $conversation,
        'I am the assistant response',
        $model,
        100,
        500,
        ['finish_reason' => 'stop']
    );

    expect($message->role)->toBe(Message::ROLE_ASSISTANT)
        ->and($message->ai_model_id)->toBe($model->id)
        ->and($message->input_tokens)->toBe(100)
        ->and($message->output_tokens)->toBe(500)
        ->and($message->metadata)->toBe(['finish_reason' => 'stop']);
});

test('auto generates title from first user message', function () {
    $conversation = Conversation::factory()->for($this->user)->untitled()->create();
    $provider = Provider::factory()->for($this->user)->anthropic()->create();
    $model = AiModel::factory()->for($provider)->create();

    $this->service->addUserMessage($conversation, 'What is the meaning of life?');
    $this->service->addAssistantMessage($conversation, 'The answer is 42', $model);

    expect($conversation->fresh()->title)->toBe('What is the meaning of life?');
});

test('truncates long titles', function () {
    $conversation = Conversation::factory()->for($this->user)->untitled()->create();
    $provider = Provider::factory()->for($this->user)->anthropic()->create();
    $model = AiModel::factory()->for($provider)->create();

    $longMessage = str_repeat('a', 100);
    $this->service->addUserMessage($conversation, $longMessage);
    $this->service->addAssistantMessage($conversation, 'Response', $model);

    $title = $conversation->fresh()->title;
    expect(mb_strlen($title))->toBeLessThanOrEqual(53);
    expect($title)->toEndWith('...');
});

test('can update title', function () {
    $conversation = Conversation::factory()->for($this->user)->create(['title' => 'Old title']);

    $this->service->updateTitle($conversation, 'New title');

    expect($conversation->fresh()->title)->toBe('New title');
});

test('can archive conversation', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    $this->service->archive($conversation);

    expect($conversation->fresh()->is_archived)->toBeTrue();
});

test('can unarchive conversation', function () {
    $conversation = Conversation::factory()->for($this->user)->archived()->create();

    $this->service->unarchive($conversation);

    expect($conversation->fresh()->is_archived)->toBeFalse();
});

test('can delete conversation', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    $result = $this->service->delete($conversation);

    expect($result)->toBeTrue()
        ->and(Conversation::find($conversation->id))->toBeNull()
        ->and(Conversation::withTrashed()->find($conversation->id))->not->toBeNull();
});

test('can restore conversation', function () {
    $conversation = Conversation::factory()->for($this->user)->create();
    $conversation->delete();

    $result = $this->service->restore($conversation);

    expect($result)->toBeTrue()
        ->and(Conversation::find($conversation->id))->not->toBeNull();
});

test('get recent conversations returns non-archived', function () {
    Conversation::factory()->for($this->user)->count(3)->create(['is_archived' => false]);
    Conversation::factory()->for($this->user)->count(2)->create(['is_archived' => true]);

    $recent = $this->service->getRecentConversations($this->user);

    expect($recent)->toHaveCount(3)
        ->and($recent->every(fn ($c) => ! $c->is_archived))->toBeTrue();
});

test('get archived conversations returns only archived', function () {
    Conversation::factory()->for($this->user)->count(3)->create(['is_archived' => false]);
    Conversation::factory()->for($this->user)->count(2)->create(['is_archived' => true]);

    $archived = $this->service->getArchivedConversations($this->user);

    expect($archived)->toHaveCount(2)
        ->and($archived->every(fn ($c) => $c->is_archived))->toBeTrue();
});

test('search finds conversations by title', function () {
    Conversation::factory()->for($this->user)->create(['title' => 'About Laravel']);
    Conversation::factory()->for($this->user)->create(['title' => 'About Python']);

    $results = $this->service->search($this->user, 'Laravel');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('About Laravel');
});

test('search finds conversations by message content', function () {
    $conversation = Conversation::factory()->for($this->user)->create(['title' => 'Random title']);
    Message::factory()->for($conversation)->create(['content' => 'How do I use Laravel migrations?']);

    $results = $this->service->search($this->user, 'migrations');

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($conversation->id);
});
