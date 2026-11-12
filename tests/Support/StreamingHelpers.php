<?php

/*
 * Shared helpers for tests that drive StreamController and the chat
 * component through a faked Filament context and a faked agent stream.
 */

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Livewire\CopilotChat;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Mockery\MockInterface;

/**
 * Swap the Filament facade for a minimal manager that resolves the given
 * user, panel and tenant, and hands out a default copilot plugin.
 */
function copilotTestSwapFilament($user, string $panelId = 'admin', $tenant = null, ?FilamentCopilotPlugin $plugin = null): void
{
    $plugin ??= new FilamentCopilotPlugin;

    $panel = new class($panelId)
    {
        public function __construct(private string $id) {}

        public function getId(): string
        {
            return $this->id;
        }
    };

    $guard = new class($user)
    {
        public function __construct(private $user) {}

        public function user()
        {
            return $this->user;
        }
    };

    $manager = new class($guard, $panel, $tenant, $plugin)
    {
        public function __construct(private $guard, private $panel, private $tenant, private FilamentCopilotPlugin $plugin) {}

        public function getPlugin(string $pluginId): FilamentCopilotPlugin
        {
            return $this->plugin;
        }

        public function auth()
        {
            return $this->guard;
        }

        public function setCurrentPanel(string $panelId): void {}

        public function getCurrentPanel()
        {
            return $this->panel;
        }

        public function getTenant()
        {
            return $this->tenant;
        }
    };

    app()->instance('filament', $manager);
    Filament::swap($manager);
}

/**
 * A token usage object for the installed laravel/ai version.
 */
function copilotTestUsage(int $input = 5, int $output = 7): object
{
    if (class_exists(TextUsage::class)) {
        return new TextUsage($input, $output);
    }

    return new Usage($input, $output);
}

function copilotTestTextDelta(string $text, int $offset = 0, string $messageId = 'assistant-message-1'): TextDelta
{
    return new TextDelta('event-text-'.uniqid(), $messageId, $text, time() + $offset);
}

function copilotTestToolCall(string $id, string $name, array $arguments = [], int $offset = 0): ToolCall
{
    return new ToolCall('event-call-'.uniqid(), new ToolCallData($id, $name, $arguments), time() + $offset);
}

function copilotTestToolResult(string $id, string $name, mixed $result, bool $successful = true, ?string $error = null, bool $denied = false, int $offset = 0): ToolResult
{
    return new ToolResult(
        'event-result-'.uniqid(),
        new ToolResultData($id, $name, [], $result, denied: $denied),
        $successful,
        $error,
        time() + $offset,
        $denied,
    );
}

/**
 * @param  array<int, array{id: string, tool: string, arguments?: array, reason?: ?string}>  $approvals
 */
function copilotTestApprovalRequest(array $approvals, int $offset = 0): ToolApprovalRequest
{
    return new ToolApprovalRequest(
        'event-approval-'.uniqid(),
        new Collection(array_map(fn (array $approval) => new PendingApproval(
            $approval['id'],
            $approval['tool'],
            $approval['arguments'] ?? [],
            $approval['reason'] ?? null,
        ), $approvals)),
        time() + $offset,
    );
}

function copilotTestStreamEnd(int $input = 5, int $output = 7, int $offset = 10): StreamEnd
{
    return new StreamEnd('event-end-'.uniqid(), 'stop', copilotTestUsage($input, $output), time() + $offset);
}

/**
 * Build a streamable agent response that yields the given events.
 *
 * @param  array<int, object>  $events
 */
function copilotTestStream(array $events, ?string $sdkConversationId = null): StreamableAgentResponse
{
    $response = new StreamableAgentResponse(
        invocationId: 'invocation-'.uniqid(),
        generator: function () use ($events) {
            foreach ($events as $event) {
                yield $event;
            }
        },
        meta: new Meta(provider: 'openai', model: 'gpt-4o'),
    );

    if ($sdkConversationId !== null) {
        $response->withinConversation($sdkConversationId);
    }

    return $response;
}

/**
 * Bind a mocked CopilotAgent that returns the given streams in order, and a
 * tool registry that builds no tools. Returns the mock for extra expectations.
 *
 * @param  array<int, StreamableAgentResponse>  $streams
 */
function copilotTestFakeAgent(array $streams, array $tools = []): MockInterface
{
    $toolRegistry = Mockery::mock(ToolRegistry::class);
    $toolRegistry->shouldReceive('buildTools')->andReturn($tools);
    app()->instance(ToolRegistry::class, $toolRegistry);

    $agent = Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel')->andReturnSelf();
    $agent->shouldReceive('forUser')->andReturnSelf();
    $agent->shouldReceive('continue')->andReturnSelf()->byDefault();
    $agent->shouldReceive('forTenant')->andReturnSelf();
    $agent->shouldReceive('withTools')->andReturnSelf();
    $agent->shouldReceive('withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andReturn(...$streams);
    app()->instance(CopilotAgent::class, $agent);

    return $agent;
}

/**
 * Send a request through StreamController and return the raw SSE body.
 */
function copilotTestRunStream(array $payload): string
{
    $response = app(StreamController::class)->stream(Request::create('/copilot/stream', 'POST', array_merge([
        'panel_id' => 'admin',
    ], $payload)));

    $initialBufferLevel = ob_get_level();

    // The controller discards the innermost buffer before it writes, and
    // flushes after every event, so the events land in the outermost one.
    ob_start();
    ob_start();
    ob_start();

    $response->sendContent();

    while (ob_get_level() > $initialBufferLevel + 1) {
        ob_end_flush();
    }

    return (string) ob_get_clean();
}

/**
 * A CopilotChat that records the browser events it dispatches instead of
 * queueing them on a Livewire request, so it can be driven as a plain object.
 */
function copilotTestRecordingChat(): CopilotChat
{
    return new class extends CopilotChat
    {
        /** @var array<int, array{0: string, 1: array}> */
        public array $recordedDispatches = [];

        public function dispatch($event, ...$params)
        {
            $this->recordedDispatches[] = [$event, $params];

            return null;
        }

        /**
         * @return array<int, array>
         */
        public function dispatched(string $event): array
        {
            return array_values(array_map(
                fn (array $dispatch) => $dispatch[1],
                array_filter($this->recordedDispatches, fn (array $dispatch) => $dispatch[0] === $event),
            ));
        }
    };
}

/**
 * Parse an SSE body into a list of [event, data] pairs.
 *
 * @return array<int, array{0: string, 1: array}>
 */
function copilotTestParseSse(string $body): array
{
    $events = [];

    foreach (preg_split('/\n\n/', trim($body)) as $block) {
        if (preg_match('/^event: (.+)\ndata: (.*)$/s', trim($block), $matches)) {
            $events[] = [trim($matches[1]), json_decode($matches[2], true) ?? []];
        }
    }

    return $events;
}
