<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;

/*
 * Issue #36: a conversation whose turn paused for a human tool approval
 * must be resumable — on the first turn, after later turns, and after the
 * conversation was reopened from history.
 */

function pausedTurnStream(string $sdkConversationId = 'sdk-conversation-1')
{
    return copilotTestStream([
        copilotTestTextDelta('I will delete that order for you.'),
        copilotTestToolCall('call-1', 'delete_order', ['id' => 42], 1),
        copilotTestApprovalRequest([
            ['id' => 'call-1', 'tool' => 'delete_order', 'arguments' => ['id' => 42], 'reason' => 'Deleting an order cannot be undone.'],
        ], 2),
        copilotTestStreamEnd(),
    ], $sdkConversationId);
}

function resumedTurnStream(bool $denied = false)
{
    return copilotTestStream([
        copilotTestToolResult('call-1', 'delete_order', $denied ? 'The user rejected this tool call.' : 'Order 42 deleted.', successful: ! $denied, denied: $denied),
        copilotTestTextDelta($denied ? 'Okay, I left the order alone.' : 'Order 42 has been deleted.', 1),
        copilotTestStreamEnd(),
    ], 'sdk-conversation-1');
}

it('announces the conversation id before a first-turn approval pause and persists it', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([pausedTurnStream()]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Delete order 42']));

    $conversation = CopilotConversation::query()->sole();

    // The very first event carries the id, before the pause is announced.
    expect($events[0])->toBe(['conversation', ['id' => $conversation->id]]);

    $names = array_column($events, 0);
    expect(array_search('conversation', $names))->toBeLessThan(array_search('tool_approval_request', $names));

    expect($conversation->metadata['ai_conversation_id'])->toBe('sdk-conversation-1');

    $done = collect($events)->firstWhere(0, 'done')[1];
    expect($done['approvals'])->toHaveCount(1)
        ->and($done['approvals'][0]['id'])->toBe('call-1');
});

it('stores the pending approvals of a paused turn so they can be restored', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([pausedTurnStream()]);

    copilotTestRunStream(['message' => 'Delete order 42']);

    $conversation = CopilotConversation::query()->sole();
    $approvals = app(ConversationManager::class)->getPendingApprovals($conversation);

    expect($approvals)->toBe([[
        'id' => 'call-1',
        'tool' => 'delete_order',
        'arguments' => ['id' => 42],
        'reason' => 'Deleting an order cannot be undone.',
    ]]);

    $toolCall = CopilotToolCall::query()->sole();
    expect($toolCall->status)->toBe(ToolCallStatus::Pending)
        ->and($toolCall->requires_approval)->toBeTrue()
        ->and($toolCall->provider_id)->toBe('call-1');
});

it('resumes the paused conversation when the approval is submitted with its id', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    $agent = copilotTestFakeAgent([pausedTurnStream(), resumedTurnStream()]);

    copilotTestRunStream(['message' => 'Delete order 42']);
    $conversation = CopilotConversation::query()->sole();

    $agent->shouldReceive('continue')
        ->once()
        ->withArgs(fn ($id, $as = null) => $id === 'sdk-conversation-1' && $as?->is($user))
        ->andReturnSelf();

    $body = copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['call-1' => ['action' => 'approve']],
    ]);

    expect($body)->not->toContain('cannot be resumed')
        ->and($body)->not->toContain('event: error')
        ->and(CopilotConversation::query()->count())->toBe(1);

    $toolCall = CopilotToolCall::query()->sole();
    expect($toolCall->status)->toBe(ToolCallStatus::Executed)
        ->and($toolCall->tool_output)->toBe('Order 42 deleted.');

    // Resuming adds a newer message, so nothing is pending any more.
    expect(app(ConversationManager::class)->getPendingApprovals($conversation->fresh()))->toBe([]);

    expect($conversation->messages()->where('role', MessageRole::Assistant)->latest('created_at')->latest('id')->first()->content)
        ->toBe('Order 42 has been deleted.');
});

it('keeps a rejected approval marked as rejected after the denied result streams in', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([pausedTurnStream(), resumedTurnStream(denied: true)]);

    copilotTestRunStream(['message' => 'Delete order 42']);
    $conversation = CopilotConversation::query()->sole();

    $events = copilotTestParseSse(copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['call-1' => ['action' => 'reject']],
    ]));

    expect(CopilotToolCall::query()->sole()->status)->toBe(ToolCallStatus::Rejected);

    $result = collect($events)->firstWhere(0, 'tool_result')[1];
    expect($result['denied'])->toBeTrue()
        ->and($result['success'])->toBeFalse();
});

it('logs approvals of a resumed turn that pauses again', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([
        pausedTurnStream(),
        copilotTestStream([
            copilotTestToolResult('call-1', 'delete_order', 'Order 42 deleted.'),
            copilotTestToolCall('call-2', 'delete_order', ['id' => 43], 1),
            copilotTestApprovalRequest([['id' => 'call-2', 'tool' => 'delete_order', 'arguments' => ['id' => 43]]], 2),
            copilotTestStreamEnd(),
        ], 'sdk-conversation-1'),
    ]);

    copilotTestRunStream(['message' => 'Delete orders 42 and 43']);
    $conversation = CopilotConversation::query()->sole();

    copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['call-1' => ['action' => 'approve']],
    ]);

    $second = CopilotToolCall::query()->where('provider_id', 'call-2')->sole();
    expect($second->requires_approval)->toBeTrue()
        ->and($second->status)->toBe(ToolCallStatus::Pending);

    expect(collect(app(ConversationManager::class)->getPendingApprovals($conversation->fresh()))->pluck('id')->all())
        ->toBe(['call-2']);
});

it('reports nothing pending once a new message was sent instead of deciding', function () {
    $user = createTestUser();
    $conversation = app(ConversationManager::class)->create($user, 'admin');

    $conversation->messages()->create(['role' => MessageRole::User, 'content' => 'Delete order 42']);
    $conversation->messages()->create([
        'role' => MessageRole::Assistant,
        'content' => '',
        'metadata' => ['pending_approvals' => [['id' => 'call-1', 'tool' => 'delete_order', 'arguments' => [], 'reason' => null]]],
        'created_at' => now()->subMinute(),
    ]);
    $conversation->messages()->create(['role' => MessageRole::User, 'content' => 'Never mind']);

    expect(app(ConversationManager::class)->getPendingApprovals($conversation))->toBe([]);
});

it('restores pending approvals when a paused conversation is reopened', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([pausedTurnStream()]);

    copilotTestRunStream(['message' => 'Delete order 42']);
    $conversation = CopilotConversation::query()->sole();

    $chat = copilotTestRecordingChat();
    $chat->loadConversation($conversation->id);

    expect($chat->conversationId)->toBe($conversation->id);

    $restored = $chat->dispatched('copilot-approvals-restored');

    expect($restored)->toHaveCount(1)
        ->and($restored[0]['approvals'])->toHaveCount(1)
        ->and($restored[0]['approvals'][0]['id'])->toBe('call-1')
        ->and($restored[0]['approvals'][0]['reason'])->toBe('Deleting an order cannot be undone.');
});

it('clears restored approvals when starting a new conversation', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);

    $chat = copilotTestRecordingChat();
    $chat->newConversation();

    expect($chat->dispatched('copilot-approvals-restored'))->toBe([['approvals' => []]]);
});

it('does not add an empty assistant bubble for a turn that only paused', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);

    $chat = copilotTestRecordingChat();
    $chat->handleStreamComplete('', 'conversation-1', [[
        'id' => 'call-1',
        'name' => 'delete_order',
        'arguments' => ['id' => 42],
        'status' => 'awaiting_approval',
        'result' => null,
    ]]);

    expect($chat->messages)->toHaveCount(1)
        ->and($chat->messages[0]['role'])->toBe('tool_call')
        ->and($chat->messages[0]['status'])->toBe('pending')
        ->and($chat->messages[0]['requires_approval'])->toBeTrue()
        ->and($chat->conversationId)->toBe('conversation-1');
});

it('renders a persisted tool call that awaits approval in the awaiting state', function () {
    $html = view('filament-copilot::components.chat-message', ['msg' => [
        'role' => 'tool_call',
        'tool_name' => 'delete_order',
        'arguments' => ['id' => 42],
        'result' => null,
        'success' => true,
        'error' => null,
        'content' => '',
        'status' => 'pending',
        'requires_approval' => true,
    ]])->render();

    expect($html)->toContain('data-tool-state="awaiting"');
});

it('renders a rejected tool call in the rejected state', function () {
    $html = view('filament-copilot::components.chat-message', ['msg' => [
        'role' => 'tool_call',
        'tool_name' => 'delete_order',
        'status' => 'rejected',
        'success' => false,
        'requires_approval' => true,
    ]])->render();

    expect($html)->toContain('data-tool-state="rejected"');
});

it('does not render a bubble for an empty assistant message', function () {
    $html = view('filament-copilot::components.chat-message', ['msg' => [
        'role' => 'assistant',
        'content' => '',
        'id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
        'rating' => null,
    ]])->render();

    // Only Livewire's structural block markers may remain.
    expect(trim(preg_replace('/<!--\[if (END)?BLOCK\]><!\[endif\]-->/', '', $html)))->toBe('');
});

it('sends the livewire conversation id when submitting approvals', function () {
    $view = file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');

    expect($view)->toContain('conversationId: $wire.conversationId')
        ->not->toContain('conversationId: this.conversationId')
        // The id is captured from the very first SSE event, so a turn that
        // pauses before completing still knows which conversation it is.
        ->toMatch("/case 'conversation':[\\s\\S]*?\\\$wire\\.conversationId = data\\.id/");
});

it('lists a message metadata approval as pending only while the message is the newest', function () {
    $user = createTestUser();
    $conversation = app(ConversationManager::class)->create($user, 'admin');

    $conversation->messages()->create(['role' => MessageRole::User, 'content' => 'Delete order 42']);
    CopilotMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Assistant,
        'content' => 'Need approval',
        'metadata' => ['pending_approvals' => [['id' => 'call-9', 'tool' => 'delete_order', 'arguments' => ['id' => 9]]]],
    ]);

    expect(app(ConversationManager::class)->getPendingApprovals($conversation))
        ->toBe([['id' => 'call-9', 'tool' => 'delete_order', 'arguments' => ['id' => 9], 'reason' => null]]);
});
