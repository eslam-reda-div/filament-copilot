<?php

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Gateway\TextGenerationOptions;

class CopilotAgentOptionsSubclassAgent extends CopilotAgent {}

#[MaxTokens(16000)]
#[Temperature(0.9)]
#[Timeout(45)]
class CopilotAgentOptionsAttributeSubclassAgent extends CopilotAgent {}

function copilotAgentOptionsTimeout(CopilotAgent $agent): int
{
    return (fn () => $this->getTimeout(null))->call($agent);
}

it('defaults to 4096 max tokens, 0.3 temperature and a 120 second timeout', function () {
    $agent = app(CopilotAgent::class);
    $options = TextGenerationOptions::forAgent($agent);

    expect($options->maxTokens)->toBe(4096)
        ->and($options->temperature)->toBe(0.3)
        ->and(copilotAgentOptionsTimeout($agent))->toBe(120);
});

it('reads max tokens, temperature and timeout from config', function () {
    config()->set('filament-copilot.agent.max_tokens', 16000);
    config()->set('filament-copilot.agent.temperature', 0.7);
    config()->set('filament-copilot.agent.timeout', 300);

    $agent = app(CopilotAgent::class);
    $options = TextGenerationOptions::forAgent($agent);

    expect($options->maxTokens)->toBe(16000)
        ->and($options->temperature)->toBe(0.7)
        ->and(copilotAgentOptionsTimeout($agent))->toBe(300);
});

it('casts string config values from env()', function () {
    config()->set('filament-copilot.agent.max_tokens', '8192');
    config()->set('filament-copilot.agent.temperature', '0');
    config()->set('filament-copilot.agent.timeout', '90');

    $agent = app(CopilotAgent::class);

    expect($agent->maxTokens())->toBe(8192)
        ->and($agent->temperature())->toBe(0.0)
        ->and($agent->timeout())->toBe(90);
});

it('leaves max tokens and temperature to the provider when set to null', function () {
    config()->set('filament-copilot.agent.max_tokens', null);
    config()->set('filament-copilot.agent.temperature', null);

    $options = TextGenerationOptions::forAgent(app(CopilotAgent::class));

    expect($options->maxTokens)->toBeNull()
        ->and($options->temperature)->toBeNull();
});

it('applies the options to agents that extend CopilotAgent', function () {
    config()->set('filament-copilot.agent.max_tokens', 12000);

    $options = TextGenerationOptions::forAgent(app(CopilotAgentOptionsSubclassAgent::class));

    expect($options->maxTokens)->toBe(12000)
        ->and($options->temperature)->toBe(0.3);
});

it('keeps the attributes an extending agent declares over the config values', function () {
    config()->set('filament-copilot.agent.max_tokens', 8192);
    config()->set('filament-copilot.agent.temperature', 0.1);
    config()->set('filament-copilot.agent.timeout', 300);

    $agent = app(CopilotAgentOptionsAttributeSubclassAgent::class);
    $options = TextGenerationOptions::forAgent($agent);

    expect($options->maxTokens)->toBe(16000)
        ->and($options->temperature)->toBe(0.9)
        ->and(copilotAgentOptionsTimeout($agent))->toBe(45);
});

it('keeps the attributes an extending agent declares when the config values are null', function () {
    config()->set('filament-copilot.agent.max_tokens', null);
    config()->set('filament-copilot.agent.temperature', null);
    config()->set('filament-copilot.agent.timeout', null);

    $agent = app(CopilotAgentOptionsAttributeSubclassAgent::class);
    $options = TextGenerationOptions::forAgent($agent);

    expect($options->maxTokens)->toBe(16000)
        ->and($options->temperature)->toBe(0.9)
        ->and(copilotAgentOptionsTimeout($agent))->toBe(45);
});

it('falls back to a 120 second timeout when the config value is null', function () {
    config()->set('filament-copilot.agent.timeout', null);

    expect(copilotAgentOptionsTimeout(app(CopilotAgent::class)))->toBe(120);
});

it('treats blank string config values like null', function () {
    config()->set('filament-copilot.agent.max_tokens', '');
    config()->set('filament-copilot.agent.temperature', '');
    config()->set('filament-copilot.agent.timeout', '');

    $agent = app(CopilotAgent::class);
    $options = TextGenerationOptions::forAgent($agent);

    expect($options->maxTokens)->toBeNull()
        ->and($options->temperature)->toBeNull()
        ->and(copilotAgentOptionsTimeout($agent))->toBe(120);
});
