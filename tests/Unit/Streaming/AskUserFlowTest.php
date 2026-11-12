<?php

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Events\CopilotToolApprovalRequired;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Exceptions\NoSuchToolException;
use Laravel\Ai\Streaming\Events\ReasoningDelta;

/*
 * Issue #41: "Can't seem to handle LLM asking questions … the chat got stuck".
 * Questions pause the turn and are answered from the chat; reasoning, empty
 * replies and calls to unknown tools no longer leave the chat looking frozen.
 */

const ASK_ARGS = ['question' => 'Which order do you mean?', 'options' => ['#41', '#42']];

function questionPauseStream(string $textBefore = '')
{
    return copilotTestStream(array_filter([
        $textBefore !== '' ? copilotTestTextDelta($textBefore) : null,
        copilotTestToolCall('q-1', 'ask_user', ASK_ARGS, 1),
        copilotTestApprovalRequest([
            ['id' => 'q-1', 'tool' => 'ask_user', 'arguments' => ASK_ARGS, 'reason' => ASK_ARGS['question']],
        ], 2),
        copilotTestStreamEnd(),
    ]), 'sdk-conversation-q');
}

function answeredStream()
{
    return copilotTestStream([
        copilotTestToolResult('q-1', 'ask_user', 'The user answered: #42'),
        copilotTestTextDelta('Order #42 has been paid.', 1),
        copilotTestStreamEnd(),
    ], 'sdk-conversation-q');
}

/**
 * Like copilotTestFakeAgent(), but records the prompt each stream() call got.
 */
function recordingAgent(array $streams, array &$prompts): void
{
    $registry = Mockery::mock(ToolRegistry::class);
    $registry->shouldReceive('buildTools')->andReturn([]);
    app()->instance(ToolRegistry::class, $registry);

    $agent = Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel', 'forUser', 'continue', 'forTenant', 'withTools', 'withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andReturnUsing(function (...$arguments) use (&$streams, &$prompts) {
        $prompts[] = $arguments['prompt'] ?? $arguments[0];

        return array_shift($streams);
    });
    app()->instance(CopilotAgent::class, $agent);
}

beforeEach(function () {
    $this->user = createTestUser();
    copilotTestSwapFilament($this->user);
});

it('shows the question as part of the reply instead of a tool box', function () {
    Event::fake([CopilotToolApprovalRequired::class]);
    copilotTestFakeAgent([questionPauseStream('Sure, I can check that.')]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Is my order paid?']));
    $names = array_column($events, 0);

    expect($names)->not->toContain('tool_call')
        ->toContain('tool_approval_request');

    $streamed = collect($events)->where(0, 'chunk')->map(fn ($event) => $event[1]['text'])->implode('');
    expect($streamed)->toBe("Sure, I can check that.\n\nWhich order do you mean?");

    $reply = CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole();
    expect($reply->content)->toBe($streamed);

    $pause = collect($events)->firstWhere(0, 'tool_approval_request')[1]['approvals'][0];
    expect($pause['question'])->toBe('Which order do you mean?')
        ->and($pause['options'])->toBe(['#41', '#42']);

    // A question is not an action awaiting sign-off.
    Event::assertNotDispatched(CopilotToolApprovalRequired::class);
});

it('does not repeat a question the model already wrote out', function () {
    copilotTestFakeAgent([questionPauseStream('Which order do you mean?')]);

    copilotTestRunStream(['message' => 'Is my order paid?']);

    expect(CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole()->content)
        ->toBe('Which order do you mean?');
});

it('restores a pending question with its options when the conversation is reopened', function () {
    copilotTestFakeAgent([questionPauseStream()]);
    copilotTestRunStream(['message' => 'Is my order paid?']);

    $conversation = CopilotConversation::query()->sole();

    expect(app(ConversationManager::class)->getPendingApprovals($conversation))->toBe([[
        'id' => 'q-1',
        'tool' => 'ask_user',
        'arguments' => ASK_ARGS,
        'reason' => 'Which order do you mean?',
        'question' => 'Which order do you mean?',
        'options' => ['#41', '#42'],
    ]]);

    // The transcript shows the question as text, not as an ask_user tool box.
    $chat = app(ConversationManager::class)->getMessagesForChat($conversation);
    expect(collect($chat)->where('role', 'tool_call')->all())->toBe([]);
});

it('resumes the paused turn with the answer and keeps the answer in the transcript', function () {
    $prompts = [];
    recordingAgent([questionPauseStream(), answeredStream()], $prompts);

    copilotTestRunStream(['message' => 'Is my order paid?']);
    $conversation = CopilotConversation::query()->sole();

    $events = copilotTestParseSse(copilotTestRunStream([
        'conversation_id' => $conversation->id,
        // Arguments from the browser are ignored; the stored pause is used.
        'decisions' => ['q-1' => ['action' => 'answer', 'answer' => '#42', 'arguments' => ['question' => 'tampered']]],
    ]));

    expect(array_column($events, 0))->not->toContain('error')
        ->not->toContain('tool_result');

    /** @var Decisions $decisions */
    $decisions = $prompts[1];
    $decision = $decisions->get('q-1');

    expect($decisions)->toBeInstanceOf(Decisions::class)
        ->and($decision->action)->toBe('edit')
        ->and($decision->arguments)->toBe([...ASK_ARGS, 'answer' => '#42']);

    $messages = $conversation->messages()->orderBy('created_at')->orderBy('id')->get();
    expect($messages->where('role', MessageRole::User)->pluck('content')->values()->all())
        ->toBe(['Is my order paid?', '#42'])
        ->and($messages->last()->content)->toBe('Order #42 has been paid.');

    $toolCall = CopilotToolCall::query()->where('provider_id', 'q-1')->sole();
    expect($toolCall->status)->toBe(ToolCallStatus::Executed)
        ->and($toolCall->tool_output)->toBe('The user answered: #42');

    expect(app(ConversationManager::class)->getPendingApprovals($conversation->fresh()))->toBe([]);
});

it('requires an answer for an answer decision', function () {
    copilotTestFakeAgent([]);

    $conversation = app(ConversationManager::class)->create($this->user, 'admin');

    expect(fn () => app(StreamController::class)->stream(Request::create('/copilot/stream', 'POST', [
        'panel_id' => 'admin',
        'conversation_id' => $conversation->id,
        'decisions' => ['q-1' => ['action' => 'answer']],
    ])))->toThrow(ValidationException::class);
});

it('rejects an unknown decision action', function () {
    copilotTestFakeAgent([]);

    expect(fn () => app(StreamController::class)->stream(Request::create('/copilot/stream', 'POST', [
        'panel_id' => 'admin',
        'decisions' => ['q-1' => ['action' => 'delete-everything']],
    ])))->toThrow(ValidationException::class);
});

it('streams reasoning so the chat shows the model is thinking', function () {
    copilotTestFakeAgent([copilotTestStream([
        new ReasoningDelta('event-r1', 'reasoning-1', 'Let me think. ', time()),
        new ReasoningDelta('event-r2', 'reasoning-1', 'Revenue is up.', time()),
        copilotTestTextDelta('Revenue grew 12%.', 1),
        copilotTestStreamEnd(),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'How is revenue?']));

    expect(collect($events)->where(0, 'reasoning')->map(fn ($event) => $event[1]['text'])->implode(''))
        ->toBe('Let me think. Revenue is up.');

    // Reasoning is not part of the stored reply.
    expect(CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole()->content)
        ->toBe('Revenue grew 12%.');
});

it('can show the thinking indicator without the reasoning text', function () {
    config()->set('filament-copilot.chat.stream_reasoning', false);

    copilotTestFakeAgent([copilotTestStream([
        new ReasoningDelta('event-r1', 'reasoning-1', 'secret chain of thought', time()),
        copilotTestTextDelta('Done.', 1),
        copilotTestStreamEnd(),
    ])]);

    $body = copilotTestRunStream(['message' => 'Hi']);

    expect($body)->toContain('event: reasoning')
        ->not->toContain('secret chain of thought');
});

it('tells the user when a turn produced no reply at all', function () {
    copilotTestFakeAgent([copilotTestStream([copilotTestStreamEnd()])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Hello?']));

    expect(collect($events)->firstWhere(0, 'notice')[1]['message'] ?? null)
        ->toBe(__('filament-copilot::filament-copilot.empty_response'));
});

it('sends no notice when the turn wrote text or used tools', function (array $events) {
    copilotTestFakeAgent([copilotTestStream($events)]);

    expect(copilotTestRunStream(['message' => 'Hello?']))->not->toContain('event: notice');
})->with([
    'text' => [[copilotTestTextDelta('Hi!'), copilotTestStreamEnd()]],
    'tools only' => [[copilotTestToolCall('c-1', 'list_orders'), copilotTestToolResult('c-1', 'list_orders', '[]'), copilotTestStreamEnd()]],
]);

it('names the unavailable tool in the error the user sees', function () {
    $registry = Mockery::mock(ToolRegistry::class);
    $registry->shouldReceive('buildTools')->andReturn([]);
    app()->instance(ToolRegistry::class, $registry);

    $agent = Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel', 'forUser', 'forTenant', 'withTools', 'withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andThrow(new NoSuchToolException('question'));
    app()->instance(CopilotAgent::class, $agent);

    $error = collect(copilotTestParseSse(copilotTestRunStream(['message' => 'Hi'])))->firstWhere(0, 'error')[1]['message'];

    expect($error)->toContain('(question)')
        ->toMatch('/ref: [0-9A-Za-z]{6}/');
});

it('adds a stream notice to the chat as a system message', function () {
    $chat = copilotTestRecordingChat();
    $chat->handleStreamNotice('Nothing came back.');

    expect($chat->messages)->toBe([['role' => 'system', 'content' => 'Nothing came back.']]);
});

it('does not turn a question into a tool box when the stream completes', function () {
    $chat = copilotTestRecordingChat();
    $chat->handleStreamComplete('Which order do you mean?', 'conversation-1', [
        ['id' => 'q-1', 'name' => 'ask_user', 'arguments' => ASK_ARGS, 'status' => 'awaiting_approval'],
        ['id' => 'c-1', 'name' => 'list_orders', 'arguments' => [], 'status' => 'done', 'result' => '[]'],
    ], 'message-1');

    expect(collect($chat->messages)->pluck('role')->all())->toBe(['tool_call', 'assistant'])
        ->and($chat->messages[0]['tool_name'])->toBe('list_orders');
});

it('renders the question card with one click answers, a skip link and a translated hint', function () {
    app()->setLocale('de');

    $source = file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');
    preg_match('/(<div x-show="questions\.length && !isStreaming".*?)\{\{-- Human approval requests --\}\}/s', $source, $matches);

    $html = Blade::render($matches[1]);

    expect($html)->toContain('data-copilot-question-card')
        ->toContain('chooseOption(option)')
        ->toContain('skipQuestion()')
        ->toContain('fi-btn')
        ->toContain('Der Copilot braucht deine Eingabe:')
        ->toContain('Wähle eine Antwort oder schreib unten deine eigene.')
        ->toContain('Überspringen');

    // Approvals and questions are listed separately.
    expect($source)->toContain('x-for="approval in approvals"')
        ->toContain('x-show="approvals.length"')
        ->toContain('this.answerQuestion(payload.message)');
});
