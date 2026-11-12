<?php

use EslamRedaDiv\FilamentCopilot\Agent\Middleware\RateLimitMiddleware;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Models\CopilotTokenUsage;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Support\TokenUsage;
use Laravel\Ai\Responses\Data\ToolResult as ToolResultData;
use Laravel\Ai\Streaming\Events\ToolResult;

/*
 * Issue #40: run on laravel/ai ^1.0 as well as ^0.10 / ^0.11.
 */

it('reads token counts from both laravel ai usage shapes', function () {
    $legacy = new class
    {
        public int $promptTokens = 11;

        public int $completionTokens = 22;
    };

    $current = new class
    {
        public int $inputTokens = 33;

        public int $outputTokens = 44;
    };

    expect(TokenUsage::input($legacy))->toBe(11)
        ->and(TokenUsage::output($legacy))->toBe(22)
        ->and(TokenUsage::input($current))->toBe(33)
        ->and(TokenUsage::output($current))->toBe(44)
        ->and(TokenUsage::input(null))->toBe(0)
        ->and(TokenUsage::output(new stdClass))->toBe(0);
});

it('records the token usage reported by the installed laravel ai version', function () {
    config()->set('filament-copilot.rate_limits.enabled', true);

    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([copilotTestStream([
        copilotTestTextDelta('Revenue is up.'),
        copilotTestStreamEnd(input: 120, output: 45),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'How is revenue?']));

    $message = CopilotMessage::query()->where('role', MessageRole::Assistant->value)->sole();

    expect($message->input_tokens)->toBe(120)
        ->and($message->output_tokens)->toBe(45)
        ->and(collect($events)->firstWhere(0, 'usage')[1])->toBe(['input_tokens' => 120, 'output_tokens' => 45]);

    $usage = CopilotTokenUsage::query()->sole();
    expect($usage->input_tokens)->toBe(120)
        ->and($usage->output_tokens)->toBe(45);
});

it('ignores preliminary sub agent results and logs only the final one', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);

    $preliminary = new ToolResult(
        'event-preliminary',
        new ToolResultData('call-1', 'research_agent', [], 'Searching…'),
        true,
        null,
        time(),
        preliminary: true,
    );

    copilotTestFakeAgent([copilotTestStream([
        copilotTestToolCall('call-1', 'research_agent'),
        $preliminary,
        copilotTestToolResult('call-1', 'research_agent', 'Found 3 sources.', offset: 2),
        copilotTestTextDelta('Here is what I found.', 3),
        copilotTestStreamEnd(),
    ])]);

    $events = copilotTestParseSse(copilotTestRunStream(['message' => 'Research this']));
    $results = collect($events)->where(0, 'tool_result')->values();

    expect($results)->toHaveCount(1)
        ->and($results[0][1]['result'])->toBe('Found 3 sources.')
        ->and(CopilotToolCall::query()->sole()->tool_output)->toBe('Found 3 sources.');
})->skip(
    fn () => ! in_array('preliminary', array_column((new ReflectionClass(ToolResult::class))->getConstructor()->getParameters(), 'name'), true),
    'Preliminary tool results only exist on laravel/ai 1.x.',
);

it('treats a laravel ai 0.x prompt and the first 1.x step as the start of a run', function () {
    $legacyPrompt = new stdClass;

    $firstStep = new class
    {
        public function isFirstStep(): bool
        {
            return true;
        }
    };

    $laterStep = new class
    {
        public function isFirstStep(): bool
        {
            return false;
        }
    };

    expect(RateLimitMiddleware::startsRun($legacyPrompt))->toBeTrue()
        ->and(RateLimitMiddleware::startsRun($firstStep))->toBeTrue()
        ->and(RateLimitMiddleware::startsRun($laterStep))->toBeFalse();
});

it('allows laravel ai 1.x in composer.json while keeping 0.10 and 0.11', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../../composer.json'), true);
    $constraint = $composer['require']['laravel/ai'];

    expect($constraint)->toContain('^0.10')
        ->toContain('^0.11')
        ->toContain('^1.0');
});
