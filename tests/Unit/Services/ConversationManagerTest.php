<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;

it('creates a conversation', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');

    expect($conversation)->toBeInstanceOf(CopilotConversation::class)
        ->and($conversation->panel_id)->toBe('admin')
        ->and($conversation->participant_id)->toBe($user->getKey());
});

it('adds user message to conversation', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $message = $manager->addUserMessage($conversation, 'Hello bot');

    expect($message)->toBeInstanceOf(CopilotMessage::class)
        ->and($message->role)->toBe(MessageRole::User)
        ->and($message->content)->toBe('Hello bot');
});

it('adds assistant message with token tracking', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $message = $manager->addAssistantMessage(
        conversation: $conversation,
        content: 'Hello user',
        inputTokens: 50,
        outputTokens: 100,
    );

    expect($message->role)->toBe(MessageRole::Assistant)
        ->and($message->content)->toBe('Hello user')
        ->and($message->input_tokens)->toBe(50)
        ->and($message->output_tokens)->toBe(100);
});

it('gets conversations for a user', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $manager->create($user, 'admin');
    $manager->create($user, 'admin');

    $conversations = $manager->getConversations($user, 'admin');

    expect($conversations)->toHaveCount(2);
});

it('deletes a conversation', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $manager->addUserMessage($conversation, 'Test');

    $manager->delete($conversation);

    expect(CopilotConversation::count())->toBe(0);
});

it('updates conversation title', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $manager->updateTitle($conversation, 'New Title');

    expect($conversation->fresh()->title)->toBe('New Title');
});

it('gets messages formatted for agent', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $manager->addUserMessage($conversation, 'Hello');
    $manager->addAssistantMessage($conversation, 'Hi there');

    $messages = $manager->getMessagesForAgent($conversation);

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[0]['content'])->toBe('Hello')
        ->and($messages[1]['role'])->toBe('assistant');
});

it('gets messages formatted for chat with tool calls in order', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');

    $userMessage = $manager->addUserMessage($conversation, 'Generate a report for the last 7 days');
    $userMessage->toolCalls()->create([
        'tool_name' => 'run_report',
        'tool_input' => ['days' => 7],
        'tool_output' => 'Report generated',
        'status' => ToolCallStatus::Executed,
    ]);

    $manager->addAssistantMessage($conversation, 'Done. I generated the report.');

    $messages = $manager->getMessagesForChat($conversation);

    expect($messages)->toHaveCount(3)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[1]['role'])->toBe('tool_call')
        ->and($messages[1]['tool_name'])->toBe('run_report')
        ->and($messages[1]['arguments'])->toMatchArray(['days' => 7])
        ->and($messages[1]['result'])->toBe('Report generated')
        ->and($messages[2]['role'])->toBe('assistant');
});

it('gets messages formatted for chat with the message id and rating', function () {
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');

    $userMessage = $manager->addUserMessage($conversation, 'Generate a report for the last 7 days');
    $assistantMessage = $manager->addAssistantMessage($conversation, 'Done. I generated the report.');
    $assistantMessage->update(['rating' => MessageRating::Negative]);

    $messages = $manager->getMessagesForChat($conversation);

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['id'])->toBe($userMessage->id)
        ->and($messages[0]['rating'])->toBeNull()
        ->and($messages[1]['id'])->toBe($assistantMessage->id)
        ->and($messages[1]['rating'])->toBe('negative');
});
