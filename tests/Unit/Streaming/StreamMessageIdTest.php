<?php

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;

function swapFilamentForStreamMessageIdTest($user, string $panelId = 'admin'): void
{
    $plugin = new FilamentCopilotPlugin;

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

    $manager = new class($guard, $panel, $plugin)
    {
        public function __construct(private $guard, private $panel, private FilamentCopilotPlugin $plugin) {}

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
            return null;
        }
    };

    app()->instance('filament', $manager);
    Filament::swap($manager);
}

function fakeTextOnlyAgent(string $reply): void
{
    $toolRegistry = Mockery::mock(ToolRegistry::class);
    $toolRegistry->shouldReceive('buildTools')->andReturn([]);
    app()->instance(ToolRegistry::class, $toolRegistry);

    $timestamp = time();

    $streamResponse = new StreamableAgentResponse(
        invocationId: 'invocation-1',
        generator: function () use ($reply, $timestamp) {
            yield new TextDelta('event-1', 'assistant-message-1', $reply, $timestamp);
            yield new StreamEnd('event-2', 'stop', new Usage(promptTokens: 5, completionTokens: 7), $timestamp + 1);
        },
        meta: new Meta(provider: 'openai', model: 'gpt-4o'),
    );

    $agent = Mockery::mock(CopilotAgent::class);
    $agent->shouldReceive('forPanel')->andReturnSelf();
    $agent->shouldReceive('forUser')->andReturnSelf();
    $agent->shouldReceive('forTenant')->andReturnSelf();
    $agent->shouldReceive('withTools')->andReturnSelf();
    $agent->shouldReceive('withMessages')->andReturnSelf();
    $agent->shouldReceive('withSystemPrompt')->andReturnSelf();
    $agent->shouldReceive('stream')->andReturn($streamResponse);
    app()->instance(CopilotAgent::class, $agent);
}

/**
 * Run a stream request and return the raw SSE body.
 *
 * Three buffer levels: the controller discards the innermost one before it
 * starts writing, and `sendSseEvent()` calls `ob_flush()` after every event —
 * so the events land in the middle level and are flushed into the outermost
 * one, which is what gets returned.
 */
function captureStreamOutput(string $message = 'What is the total revenue?'): string
{
    $response = app(StreamController::class)->stream(Request::create('/copilot/stream', 'POST', [
        'message' => $message,
        'panel_id' => 'admin',
    ]));

    $initialBufferLevel = ob_get_level();

    ob_start();
    ob_start();
    ob_start();

    $response->sendContent();

    while (ob_get_level() > $initialBufferLevel + 1) {
        ob_end_flush();
    }

    return (string) ob_get_clean();
}

it('sends the persisted assistant message id in the done event', function () {
    $user = createTestUser();
    swapFilamentForStreamMessageIdTest($user);
    fakeTextOnlyAgent('Revenue is $1,200 this month.');

    $output = captureStreamOutput();

    $assistantMessage = CopilotMessage::query()
        ->where('role', MessageRole::Assistant->value)
        ->firstOrFail();

    expect($output)->toContain('event: done')
        ->toContain('"message_id":"'.$assistantMessage->id.'"');
});
