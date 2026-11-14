<?php

use EslamRedaDiv\FilamentCopilot\Agent\ContextBuilder;
use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Discovery\PageInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\ResourceInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\WidgetInspector;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;

/*
 * The system prompt must reach the model once: the config value replaces the
 * base prompt, and the plugin's fluent value is appended as additional instructions.
 */

function systemPromptTestInstructions(FilamentCopilotPlugin $plugin): string
{
    $builder = new class(app(ResourceInspector::class), app(PageInspector::class), app(WidgetInspector::class)) extends ContextBuilder
    {
        protected function buildResourceContext(): string
        {
            return '';
        }

        protected function buildPageContext(): string
        {
            return '';
        }

        protected function buildWidgetContext(): string
        {
            return '';
        }

        protected function buildMemoryContext(): string
        {
            return '';
        }
    };

    $agent = (new CopilotAgent($builder))
        ->forPanel('admin')
        ->forUser(createTestUser())
        ->forTenant(null)
        ->withSystemPrompt($plugin->getSystemPrompt());

    return (string) $agent->instructions();
}

it('includes a config-only system prompt exactly once', function () {
    config()->set('filament-copilot.system_prompt', 'CONFIG-PROMPT-MARKER');

    $instructions = systemPromptTestInstructions(FilamentCopilotPlugin::make());

    expect(substr_count($instructions, 'CONFIG-PROMPT-MARKER'))->toBe(1)
        ->and($instructions)->not->toContain('Additional Instructions');
});

it('includes a fluent-only system prompt once, after the default base prompt', function () {
    config()->set('filament-copilot.system_prompt', null);

    $instructions = systemPromptTestInstructions(FilamentCopilotPlugin::make()->systemPrompt('FLUENT-PROMPT-MARKER'));

    expect(substr_count($instructions, 'FLUENT-PROMPT-MARKER'))->toBe(1)
        ->and($instructions)->toContain('helpful Filament admin panel assistant')
        ->and($instructions)->toContain("## Additional Instructions\nFLUENT-PROMPT-MARKER");
});

it('includes both prompts once each when config and plugin differ', function () {
    config()->set('filament-copilot.system_prompt', 'CONFIG-PROMPT-MARKER');

    $instructions = systemPromptTestInstructions(FilamentCopilotPlugin::make()->systemPrompt('FLUENT-PROMPT-MARKER'));

    expect(substr_count($instructions, 'CONFIG-PROMPT-MARKER'))->toBe(1)
        ->and(substr_count($instructions, 'FLUENT-PROMPT-MARKER'))->toBe(1);
});

it('includes the prompt once when the plugin sets the same text as the config', function () {
    config()->set('filament-copilot.system_prompt', 'SAME-PROMPT-MARKER');

    $instructions = systemPromptTestInstructions(FilamentCopilotPlugin::make()->systemPrompt('SAME-PROMPT-MARKER'));

    expect(substr_count($instructions, 'SAME-PROMPT-MARKER'))->toBe(1);
});
