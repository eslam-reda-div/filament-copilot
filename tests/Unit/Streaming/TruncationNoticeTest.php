<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use Laravel\Ai\Responses\Data\FinishReason;

/*
 * A run that stops because it hit the output token limit (finish reason
 * `length`) is cut off mid-sentence, or empty when a reasoning model spent
 * the budget thinking. The chat says so, live and after a reload, instead of
 * passing it off as a complete answer.
 */

beforeEach(function () {
    $this->user = createTestUser();
    copilotTestSwapFilament($this->user);
});

function truncationTestNotices(array $events): array
{
    return collect($events)->where(0, 'notice')->map(fn (array $event) => $event[1]['message'])->values()->all();
}

function truncationTestAssistantMessage(): CopilotMessage
{
    return CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole();
}

it('uses the finish reason value laravel/ai reports for the output token limit', function () {
    expect(FinishReason::Length->value)->toBe('length');
});

it('sends a notice before done when the reply hit the output token limit', function () {
    copilotTestFakeAgent([copilotTestStream([
        copilotTestTextDelta('The three main causes are: first, the'),
        copilotTestStreamEnd(reason: 'length'),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Why?']));
    $names = array_column($events, 0);

    expect(truncationTestNotices($events))->toBe([__('filament-copilot::filament-copilot.response_truncated')])
        ->and(array_search('notice', $names))->toBeLessThan(array_search('done', $names));

    $message = truncationTestAssistantMessage();
    expect($message->content)->toBe('The three main causes are: first, the')
        ->and($message->metadata)->toBe(['finish_reason' => 'length']);
});

it('explains an empty reply that hit the limit as truncated, not as no response', function () {
    copilotTestFakeAgent([copilotTestStream([copilotTestStreamEnd(reason: 'length')])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Think hard.']));

    expect(truncationTestNotices($events))->toBe([__('filament-copilot::filament-copilot.response_truncated')]);
});

it('sends no notice for a reply that finished normally', function () {
    copilotTestFakeAgent([copilotTestStream([
        copilotTestTextDelta('All done.'),
        copilotTestStreamEnd(),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Hi']));

    expect(truncationTestNotices($events))->toBe([])
        ->and(truncationTestAssistantMessage()->metadata)->toBe(['finish_reason' => 'stop']);
});

it('goes by the reason of the final stream end only', function (array $reasons, bool $truncated) {
    copilotTestFakeAgent([copilotTestStream([
        copilotTestTextDelta('Step one.'),
        copilotTestStreamEnd(reason: $reasons[0]),
        copilotTestTextDelta('Step two.', 11),
        copilotTestStreamEnd(offset: 12, reason: $reasons[1]),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Go']));

    expect(truncationTestNotices($events) !== [])->toBe($truncated)
        ->and(truncationTestAssistantMessage()->metadata['finish_reason'])->toBe($reasons[1]);
})->with([
    'cut off earlier, finished last' => [['length', 'stop'], false],
    'finished earlier, cut off last' => [['tool_calls', 'length'], true],
]);

it('sends no truncation notice for a turn that paused for approval, and keeps its pending approvals', function (string $reason) {
    copilotTestFakeAgent([copilotTestStream([
        copilotTestTextDelta('I will delete that order for you.'),
        copilotTestToolCall('call-1', 'delete_order', ['id' => 42], 1),
        copilotTestApprovalRequest([
            ['id' => 'call-1', 'tool' => 'delete_order', 'arguments' => ['id' => 42], 'reason' => 'Cannot be undone.'],
        ], 2),
        copilotTestStreamEnd(reason: $reason),
    ], 'sdk-conversation-1')]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Delete order 42']));

    expect(truncationTestNotices($events))->toBe([]);

    $metadata = truncationTestAssistantMessage()->metadata;
    expect($metadata['finish_reason'])->toBe($reason)
        ->and($metadata['pending_approvals'][0]['id'])->toBe('call-1');

    $conversation = CopilotConversation::query()->sole();
    $manager = app(ConversationManager::class);

    expect($manager->getPendingApprovals($conversation))->toHaveCount(1)
        ->and(collect($manager->getMessagesForChat($conversation))->where('role', 'system')->all())->toBe([]);
})->with([
    // What laravel/ai's loop reports for a pause on 0.11 and 1.x.
    'tool_calls' => ['tool_calls'],
    // A paused turn is never treated as cut off, whatever it reports.
    'length' => ['length'],
]);

it('sends no truncation notice for a turn that paused on a question', function () {
    $arguments = ['question' => 'Which order do you mean?', 'options' => ['#41', '#42']];

    copilotTestFakeAgent([copilotTestStream([
        copilotTestToolCall('q-1', 'ask_user', $arguments, 1),
        copilotTestApprovalRequest([
            ['id' => 'q-1', 'tool' => 'ask_user', 'arguments' => $arguments, 'reason' => $arguments['question']],
        ], 2),
        copilotTestStreamEnd(reason: 'tool_calls'),
    ], 'sdk-conversation-q')]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Is my order paid?']));

    expect(truncationTestNotices($events))->toBe([])
        ->and(truncationTestAssistantMessage()->metadata['pending_approvals'][0]['tool'])->toBe('ask_user');
});

it('shows the notice again under the cut-off reply when the conversation is reopened', function () {
    copilotTestFakeAgent([
        copilotTestStream([copilotTestTextDelta('Part one of the'), copilotTestStreamEnd(reason: 'length')], 'sdk-conversation-1'),
        copilotTestStream([copilotTestTextDelta('A complete answer.'), copilotTestStreamEnd()], 'sdk-conversation-1'),
    ]);

    copilotTestRunStream(['message' => 'Explain it all']);
    $conversation = CopilotConversation::query()->sole();
    copilotTestRunStream(['message' => 'And briefly?', 'conversation_id' => $conversation->id]);

    $rows = app(ConversationManager::class)->getMessagesForChat($conversation);

    expect(array_column($rows, 'role'))->toBe(['user', 'assistant', 'system', 'user', 'assistant'])
        ->and($rows[1]['content'])->toBe('Part one of the')
        ->and($rows[2]['content'])->toBe(__('filament-copilot::filament-copilot.response_truncated'));
});

it('translates the notice', function () {
    app()->setLocale('de');

    expect(__('filament-copilot::filament-copilot.response_truncated'))
        ->toBe('Diese Antwort wurde abgeschnitten, weil sie die maximale Antwortlänge erreicht hat.');
});

it('adds stream notices to the chat after the reply they are about', function () {
    $source = file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');

    $complete = strpos($source, "\$wire.dispatchSelf('copilot-stream-complete'");
    $notice = strpos($source, "\$wire.dispatchSelf('copilot-stream-notice'");

    expect($complete)->not->toBeFalse()
        ->and($notice)->not->toBeFalse()
        ->and($notice)->toBeGreaterThan($complete)
        ->and(substr_count($source, "'copilot-stream-notice'"))->toBe(1);
});
