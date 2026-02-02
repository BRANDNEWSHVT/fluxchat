<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Folder;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('conversation can be created with factory', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    expect($conversation)->toBeInstanceOf(Conversation::class)
        ->and($conversation->user_id)->toBe($this->user->id)
        ->and($conversation->is_archived)->toBeFalse();
});

test('conversation belongs to user', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    expect($conversation->user)->toBeInstanceOf(User::class)
        ->and($conversation->user->id)->toBe($this->user->id);
});

test('conversation can belong to folder', function () {
    $folder = Folder::factory()->for($this->user)->create();
    $conversation = Conversation::factory()->for($this->user)->inFolder($folder)->create();

    expect($conversation->folder)->toBeInstanceOf(Folder::class)
        ->and($conversation->folder_id)->toBe($folder->id);
});

test('conversation has many messages', function () {
    $conversation = Conversation::factory()->for($this->user)->create();
    Message::factory()->count(5)->for($conversation)->create();

    expect($conversation->messages)->toHaveCount(5);
});

test('conversation can be archived', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    $conversation->update(['is_archived' => true]);

    expect($conversation->fresh()->is_archived)->toBeTrue();
});

test('conversation can be soft deleted', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    $conversation->delete();

    expect($conversation->trashed())->toBeTrue()
        ->and(Conversation::withTrashed()->find($conversation->id))->not->toBeNull()
        ->and(Conversation::find($conversation->id))->toBeNull();
});

test('conversation can be restored', function () {
    $conversation = Conversation::factory()->for($this->user)->create();
    $conversation->delete();

    $conversation->restore();

    expect($conversation->trashed())->toBeFalse()
        ->and(Conversation::find($conversation->id))->not->toBeNull();
});

test('update last message at updates timestamp', function () {
    $conversation = Conversation::factory()->for($this->user)->create([
        'last_message_at' => now()->subDay(),
    ]);

    $oldTime = $conversation->last_message_at;
    $conversation->updateLastMessageAt();

    expect($conversation->fresh()->last_message_at)->toBeGreaterThan($oldTime);
});

test('messages are ordered by created_at', function () {
    $conversation = Conversation::factory()->for($this->user)->create();

    $msg1 = Message::factory()->for($conversation)->create(['created_at' => now()->subMinutes(2)]);
    $msg2 = Message::factory()->for($conversation)->create(['created_at' => now()->subMinute()]);
    $msg3 = Message::factory()->for($conversation)->create(['created_at' => now()]);

    $messages = $conversation->messages;

    expect($messages[0]->id)->toBe($msg1->id)
        ->and($messages[1]->id)->toBe($msg2->id)
        ->and($messages[2]->id)->toBe($msg3->id);
});
