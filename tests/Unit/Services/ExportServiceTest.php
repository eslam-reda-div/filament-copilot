<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ExportService;

it('exports conversation to markdown', function () {
    $user = createTestUser();
    $service = app(ExportService::class);

    $conversation = CopilotConversation::create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'panel_id' => 'admin',
        'title' => 'Test Conversation',
    ]);

    CopilotMessage::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::User,
        'content' => 'Hello!',
    ]);

    CopilotMessage::create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Hi there! How can I help?',
        'input_tokens' => 10,
        'output_tokens' => 20,
    ]);

    $markdown = $service->toMarkdown($conversation->id, $user, 'admin');

    expect($markdown)->toContain('Test Conversation')
        ->and($markdown)->toContain('Hello!')
        ->and($markdown)->toContain('Hi there! How can I help?')
        ->and($markdown)->toContain('You')
        ->and($markdown)->toContain('Copilot');
});
