<?php

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\Data\ToolCall as ToolCallData;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;

class ToolCallLoggingPanelStub
{
    public function __construct(protected string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}

class ToolCallLoggingGuardStub
{
    public function __construct(protected $user) {}

    public function user()
    {
        return $this->user;
    }
}

class ToolCallLoggingFilamentManagerStub
{
    protected ?ToolCallLoggingPanelStub $currentPanel = null;

    /** @var array<string, ToolCallLoggingPanelStub> */
    protected array $panels = [];

    public function __construct(
        protected ToolCallLoggingGuardStub $guard,
        ToolCallLoggingPanelStub $panel,
        protected FilamentCopilotPlugin $plugin,
        protected $tenant = null,
    ) {
        $this->currentPanel = $panel;
        $this->panels[$panel->getId()] = $panel;
    }

    public function getPlugin(string $pluginId): FilamentCopilotPlugin
    {
        if ($pluginId !== $this->plugin->getId()) {
            throw new RuntimeException("Plugin '{$pluginId}' not found.");
        }

        return $this->plugin;
    }

    public function auth(): ToolCallLoggingGuardStub
    {
        return $this->guard;
    }

    public function setCurrentPanel(string $panelId): void
    {
        if (! isset($this->panels[$panelId])) {
            throw new RuntimeException('Panel not found.');
        }

        $this->currentPanel = $this->panels[$panelId];
    }

    public function getCurrentPanel(): ?ToolCallLoggingPanelStub
    {
        return $this->currentPanel;
    }

    public function getTenant()
    {
        return $this->tenant;
    }
}

function swapFilamentForToolCallLoggingTest($user, ToolCallLoggingPanelStub $panel, ?FilamentCopilotPlugin $plugin = null): void
{
    $plugin ??= new FilamentCopilotPlugin;

    $manager = new ToolCallLoggingFilamentManagerStub(
        new ToolCallLoggingGuardStub($user),
        $panel,
        $plugin,
    );

    app()->instance('filament', $manager);
    Filament::swap($manager);
}

function makeToolCallStreamResponse(bool $successful = true, mixed $result = 'Tool completed', ?string $error = null): StreamableAgentResponse
{
    $timestamp = time();
    $toolName = 'run_tool';
    $arguments = [
        'source_class' => 'App\\Filament\\Resources\\OrderResource',
        'tool_class' => 'App\\Copilot\\Tools\\SearchOrdersTool',
        'arguments' => '{"status":"paid"}',
    ];

    return new StreamableAgentResponse(
        invocationId: 'invocation-1',
        generator: function () use ($timestamp, $toolName, $arguments, $successful, $result, $error) {
            yield new TextDelta('event-1', 'assistant-message-1', 'Checking that for you...', $timestamp);
            yield new ToolCall('event-2', new ToolCallData('tool-call-1', $toolName, $arguments), $timestamp + 1);
            yield new ToolResult(
                'event-3',
                new ToolResultData('tool-call-1', $toolName, $arguments, $result),
                $successful,
                $error,
                $timestamp + 2,
            );
            yield new TextDelta('event-4', 'assistant-message-1', ' Done.', $timestamp + 3);
            yield new StreamEnd('event-5', 'stop', copilotTestUsage(12, 34), $timestamp + 4);
        },
        meta: new Meta(provider: 'openai', model: 'gpt-4o'),
    );
}

function runToolCallStreamRequest(): void
{
    $response = app(StreamController::class)->stream(Request::create('/copilot/stream', 'POST', [
        'message' => 'Get me paid orders',
        'panel_id' => 'admin',
    ]));

    $initialBufferLevel = ob_get_level();

    ob_start();
    ob_start();

    $response->sendContent();

    while (ob_get_level() > $initialBufferLevel) {
        ob_end_clean();
    }
}

it('persists streamed tool calls and marks successful calls as executed', function () {
    $user = createTestUser();
    swapFilamentForToolCallLoggingTest($user, new ToolCallLoggingPanelStub('admin'));

    $toolRegistry = \Mockery::mock(ToolRegistry::class);
    $toolRegistry->shouldReceive('buildTools')->andReturn([]);
    app()->instance(ToolRegistry::class, $toolRegistry);

    $agent = \Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel')->andReturnSelf();
    $agent->shouldReceive('forUser')->andReturnSelf();
    $agent->shouldReceive('forTenant')->andReturnSelf();
    $agent->shouldReceive('withTools')->andReturnSelf();
    $agent->shouldReceive('withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andReturn(makeToolCallStreamResponse());
    app()->instance(CopilotAgent::class, $agent);

    runToolCallStreamRequest();

    $toolCall = CopilotToolCall::query()->first();

    expect($toolCall)->not->toBeNull()
        ->and($toolCall->tool_name)->toBe('run_tool')
        ->and($toolCall->tool_input)->toMatchArray([
            'source_class' => 'App\\Filament\\Resources\\OrderResource',
            'tool_class' => 'App\\Copilot\\Tools\\SearchOrdersTool',
            'arguments' => '{"status":"paid"}',
        ])
        ->and($toolCall->tool_output)->toBe('Tool completed')
        ->and($toolCall->status)->toBe(ToolCallStatus::Executed)
        ->and($toolCall->message->role)->toBe(MessageRole::User);
});

it('persists failed streamed tool calls with failed status', function () {
    $user = createTestUser();
    swapFilamentForToolCallLoggingTest($user, new ToolCallLoggingPanelStub('admin'));

    $toolRegistry = \Mockery::mock(ToolRegistry::class);
    $toolRegistry->shouldReceive('buildTools')->andReturn([]);
    app()->instance(ToolRegistry::class, $toolRegistry);

    $agent = \Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel')->andReturnSelf();
    $agent->shouldReceive('forUser')->andReturnSelf();
    $agent->shouldReceive('forTenant')->andReturnSelf();
    $agent->shouldReceive('withTools')->andReturnSelf();
    $agent->shouldReceive('withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andReturn(makeToolCallStreamResponse(false, 'Permission denied', 'Permission denied'));
    app()->instance(CopilotAgent::class, $agent);

    runToolCallStreamRequest();

    $toolCall = CopilotToolCall::query()->first();

    expect($toolCall)->not->toBeNull()
        ->and($toolCall->status)->toBe(ToolCallStatus::Failed)
        ->and($toolCall->tool_output)->toBe('Permission denied');
});
