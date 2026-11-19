<?php

use EslamRedaDiv\FilamentCopilot\Agent\ContextBuilder;
use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAuditLog;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use EslamRedaDiv\FilamentCopilot\Tools\AskUserTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

/*
 * The real CopilotAgent driven through laravel/ai's own generation loop
 * (Agent::fake() swaps only the provider), so the agent middleware, the
 * remembered conversation store, tool execution and approvals all run for
 * real on whichever laravel/ai version is installed (^0.10, ^0.11, ^1.0).
 */

class RealLoopEchoTool implements Tool
{
    public static int $runs = 0;

    public function name(): string
    {
        return 'echo_tool';
    }

    public function description(): Stringable|string
    {
        return 'Echoes the given text.';
    }

    public function handle(Request $request): Stringable|string
    {
        static::$runs++;

        return 'echo: '.$request['text'];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['text' => $schema->string()->required()];
    }
}

class RealLoopGuardedTool extends RealLoopEchoTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'guarded_tool';
    }
}

beforeEach(function () {
    app()->register(AiServiceProvider::class);
    $this->loadMigrationsFrom(__DIR__.'/../../../vendor/laravel/ai/database/migrations');

    RealLoopEchoTool::$runs = 0;

    $context = Mockery::mock(ContextBuilder::class);
    $context->shouldReceive('forPanel', 'forTenant', 'forUser', 'withCustomPrompt')->andReturnSelf();
    $context->shouldReceive('build')->andReturn('You are a test copilot.');
    app()->instance(ContextBuilder::class, $context);

    $this->user = createTestUser();
    copilotTestSwapFilament($this->user);
});

function realLoopTools(array $tools): void
{
    $registry = Mockery::mock(ToolRegistry::class);
    $registry->shouldReceive('buildTools')->andReturn($tools);
    app()->instance(ToolRegistry::class, $registry);
}

/**
 * Script the fake provider: the first call asks for the given tool call,
 * every later one answers with $reply. A closure rather than a list, because
 * the SDK also asks the provider for a conversation title, at a point in the
 * turn that differs between laravel/ai versions.
 */
function fakeToolCallThenReply(ToolCall $toolCall, string $reply): void
{
    $issued = false;

    CopilotAgent::fake(function () use (&$issued, $toolCall, $reply) {
        if (! $issued) {
            $issued = true;

            return $toolCall;
        }

        return $reply;
    });
}

function auditCount(AuditAction $action): int
{
    return CopilotAuditLog::query()->where('action', $action->value)->count();
}

it('streams a reply through the real agent loop', function () {
    realLoopTools([]);
    CopilotAgent::fake(['Hello from the copilot.']);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Hi']));
    $names = array_column($events, 0);

    expect($names)->not->toContain('error')
        ->toContain('chunk')
        ->toContain('done');

    $conversation = CopilotConversation::query()->sole();

    expect($conversation->messages()->where('role', MessageRole::Assistant)->sole()->content)->toBe('Hello from the copilot.')
        ->and($conversation->metadata['ai_conversation_id'] ?? null)->not->toBeNull();
});

it('runs the agent middleware once per turn even when the turn takes several steps', function () {
    config()->set('filament-copilot.rate_limits.enabled', true);

    $rateLimits = Mockery::mock(RateLimitService::class)->makePartial();
    app()->instance(RateLimitService::class, $rateLimits);

    realLoopTools([new RealLoopEchoTool]);
    fakeToolCallThenReply(new ToolCall('call-1', 'echo_tool', ['text' => 'hi']), 'The tool said hi.');

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Echo hi']));

    expect(array_column($events, 0))->not->toContain('error')
        ->toContain('tool_call')
        ->toContain('tool_result');

    expect(RealLoopEchoTool::$runs)->toBe(1)
        ->and(auditCount(AuditAction::MessageSent))->toBe(1)
        ->and(auditCount(AuditAction::ResponseReceived))->toBe(1);

    // The controller checks once, the middleware once at the start of the
    // run, and the message is recorded once, however many steps there were.
    $rateLimits->shouldHaveReceived('canSendMessage')->twice();
    $rateLimits->shouldHaveReceived('recordMessage')->once();

    $toolCall = CopilotToolCall::query()->sole();
    expect($toolCall->status)->toBe(ToolCallStatus::Executed)
        ->and($toolCall->tool_output)->toBe('echo: hi');

    $reply = CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole()->content;
    expect($reply)->toBe('The tool said hi.');
});

it('records why the run stopped on the reply, with no truncation notice for a normal finish', function () {
    realLoopTools([new RealLoopEchoTool]);
    fakeToolCallThenReply(new ToolCall('call-1', 'echo_tool', ['text' => 'hi']), 'The tool said hi.');

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Echo hi']));

    // Several steps, but only the final one's reason is kept.
    expect(array_column($events, 0))->not->toContain('notice')
        ->and(CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole()->metadata)
        ->toBe(['finish_reason' => 'stop']);
});

it('blocks a turn in the agent middleware when the rate limit is exhausted', function () {
    config()->set('filament-copilot.rate_limits.enabled', true);

    $rateLimits = Mockery::mock(RateLimitService::class)->makePartial();
    // Let the controller's own pre-check pass, so the middleware is what blocks.
    $rateLimits->shouldReceive('canSendMessage')->andReturn(true, false);
    app()->instance(RateLimitService::class, $rateLimits);

    realLoopTools([]);
    CopilotAgent::fake(['should not be streamed']);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Hi']));

    expect(array_column($events, 0))->toContain('error')
        ->and(collect($events)->where(0, 'chunk')->all())->toBe([]);
});

/**
 * laravel/ai 0.x's Agent::fake() never applies approval decisions (its
 * provider only resumes "against a real gateway"), so a faked resume can
 * only be exercised on 1.x. The pause itself is covered on every version.
 */
function fakeCanResumeApprovals(): bool
{
    return class_exists(PendingStep::class);
}

it('pauses for approval through the real loop', function () {
    realLoopTools([(new RealLoopGuardedTool)->requireApproval('This needs a human.')]);
    fakeToolCallThenReply(new ToolCall('call-1', 'guarded_tool', ['text' => 'secret']), 'Done after approval.');

    $first = copilotTestParseSse(copilotTestRunStream(['message' => 'Run the guarded tool']));

    $approval = collect($first)->firstWhere(0, 'tool_approval_request');
    expect($approval)->not->toBeNull()
        ->and($approval[1]['approvals'][0]['id'])->toBe('call-1')
        ->and($approval[1]['approvals'][0]['reason'])->toBe('This needs a human.')
        ->and(RealLoopEchoTool::$runs)->toBe(0);

    $conversation = CopilotConversation::query()->sole();
    expect($conversation->metadata['ai_conversation_id'] ?? null)->not->toBeNull()
        ->and(app(ConversationManager::class)->getPendingApprovals($conversation))
        ->toHaveCount(1);

    // A pause is not a truncated reply: the loop ends it with `tool_calls`,
    // stored next to (not instead of) the pending approvals.
    $metadata = $conversation->messages()->where('role', MessageRole::Assistant)->sole()->metadata;
    expect(array_column($first, 0))->not->toContain('notice')
        ->and($metadata['finish_reason'])->toBe('tool_calls')
        ->and($metadata['pending_approvals'][0]['id'])->toBe('call-1');
});

it('resumes an approved turn in the same conversation through the real loop', function () {
    realLoopTools([(new RealLoopGuardedTool)->requireApproval('This needs a human.')]);
    fakeToolCallThenReply(new ToolCall('call-1', 'guarded_tool', ['text' => 'secret']), 'Done after approval.');

    copilotTestRunStream(['message' => 'Run the guarded tool']);
    $conversation = CopilotConversation::query()->sole();

    $second = copilotTestParseSse(copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['call-1' => ['action' => 'approve']],
    ]));

    expect(array_column($second, 0))->not->toContain('error')
        ->toContain('tool_result')
        ->and(RealLoopEchoTool::$runs)->toBe(1)
        ->and(collect($second)->where(0, 'chunk')->map(fn ($event) => $event[1]['text'])->implode(''))
        ->toContain('Done after approval.')
        ->and(CopilotConversation::query()->count())->toBe(1);

    expect(CopilotToolCall::query()->where('provider_id', 'call-1')->sole()->status)->toBe(ToolCallStatus::Executed);
})->skip(fn () => ! fakeCanResumeApprovals(), 'laravel/ai 0.x fakes cannot resume approvals.');

it('feeds a rejection back to the model without running the tool', function () {
    realLoopTools([(new RealLoopGuardedTool)->requireApproval()]);
    fakeToolCallThenReply(new ToolCall('call-1', 'guarded_tool', ['text' => 'secret']), 'Understood, I will not run it.');

    copilotTestRunStream(['message' => 'Run the guarded tool']);
    $conversation = CopilotConversation::query()->sole();

    $second = copilotTestParseSse(copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['call-1' => ['action' => 'reject']],
    ]));

    expect(array_column($second, 0))->not->toContain('error')
        ->toContain('tool_result')
        ->and(RealLoopEchoTool::$runs)->toBe(0)
        ->and(CopilotToolCall::query()->where('provider_id', 'call-1')->sole()->status)->toBe(ToolCallStatus::Rejected);
})->skip(fn () => ! fakeCanResumeApprovals(), 'laravel/ai 0.x fakes cannot resume approvals.');

it('pauses on a clarifying question through the real loop', function () {
    realLoopTools([new AskUserTool]);
    fakeToolCallThenReply(new ToolCall('q-1', 'ask_user', ['question' => 'Which order?', 'options' => ['#41', '#42']]), 'Order #42 is paid.');

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Is my order paid?']));

    $pause = collect($events)->firstWhere(0, 'tool_approval_request');
    expect($pause)->not->toBeNull()
        ->and($pause[1]['approvals'][0]['question'])->toBe('Which order?')
        ->and($pause[1]['approvals'][0]['options'])->toBe(['#41', '#42'])
        ->and(collect($events)->where(0, 'chunk')->map(fn ($event) => $event[1]['text'])->implode(''))->toBe('Which order?')
        ->and(array_column($events, 0))->not->toContain('error')->not->toContain('tool_call')->not->toContain('notice');

    expect(CopilotConversation::query()->sole()->messages()->where('role', MessageRole::Assistant)->sole()->metadata['finish_reason'])
        ->toBe('tool_calls');
});

it('answers a clarifying question and continues the turn through the real loop', function () {
    realLoopTools([new AskUserTool]);
    fakeToolCallThenReply(new ToolCall('q-1', 'ask_user', ['question' => 'Which order?', 'options' => ['#41', '#42']]), 'Order #42 is paid.');

    copilotTestRunStream(['message' => 'Is my order paid?']);
    $conversation = CopilotConversation::query()->sole();

    $events = copilotTestParseSse(copilotTestRunStream([
        'conversation_id' => $conversation->id,
        'decisions' => ['q-1' => ['action' => 'answer', 'answer' => '#42']],
    ]));

    expect(array_column($events, 0))->not->toContain('error')
        ->and(collect($events)->where(0, 'chunk')->map(fn ($event) => $event[1]['text'])->implode(''))->toBe('Order #42 is paid.');

    expect(CopilotToolCall::query()->where('provider_id', 'q-1')->sole()->tool_output)->toBe('The user answered: #42')
        ->and($conversation->messages()->where('role', MessageRole::User)->pluck('content')->all())->toBe(['Is my order paid?', '#42']);
})->skip(fn () => ! fakeCanResumeApprovals(), 'laravel/ai 0.x fakes cannot resume approvals.');
