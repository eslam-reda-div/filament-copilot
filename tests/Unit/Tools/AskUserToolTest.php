<?php

use EslamRedaDiv\FilamentCopilot\Agent\ContextBuilder;
use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use EslamRedaDiv\FilamentCopilot\Tools\AskUserTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/*
 * Issue #41: give the model a structured way to ask the user a question.
 */

it('is named ask_user and always pauses with the question as the reason', function () {
    $tool = new AskUserTool;

    $approval = $tool->shouldRequestApproval(new Request(['question' => 'Which order do you mean?'], 'call-1'));

    expect($tool)->toBeInstanceOf(Approvable::class)
        ->and($tool->name())->toBe('ask_user')
        ->and($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->reason)->toBe('Which order do you mean?');
});

it('declares a question and optional options in its schema', function () {
    $schema = (new AskUserTool)->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['question', 'options']);
});

it('returns the answer to the model', function () {
    $result = (new AskUserTool)->handle(new Request(['question' => 'Which order?', 'answer' => '  #42  ']));

    expect((string) $result)->toBe('The user answered: #42');
});

it('tells the model when the question was not answered', function () {
    $result = (string) (new AskUserTool)->handle(new Request(['question' => 'Which order?']));

    expect($result)->toContain('did not answer')
        ->toContain('Do not assume an answer');
});

it('normalises the answer options the model sends', function (mixed $options, array $expected) {
    expect(AskUserTool::optionsFrom(['options' => $options]))->toBe($expected);
})->with([
    'a list' => [['Yes', 'No'], ['Yes', 'No']],
    'a comma separated string' => ['Yes, No , Maybe', ['Yes', 'No', 'Maybe']],
    'a json string' => ['["Draft","Published"]', ['Draft', 'Published']],
    'blanks and duplicates' => [['Yes', '', ' ', 'Yes', 'No'], ['Yes', 'No']],
    'non scalar entries' => [['Yes', ['nested'], null, 3], ['Yes', '3']],
    'too many' => [['1', '2', '3', '4', '5', '6', '7', '8'], ['1', '2', '3', '4', '5', '6']],
    'nothing' => [null, []],
]);

it('is registered as a built-in tool by default', function () {
    $user = createTestUser();
    copilotTestSwapFilament($user);

    $names = collect(app(ToolRegistry::class)->buildTools('admin', $user))
        ->map(fn ($tool) => method_exists($tool, 'name') ? $tool->name() : class_basename($tool));

    expect($names)->toContain('ask_user');
});

it('is not registered when clarifying questions are disabled', function () {
    config()->set('filament-copilot.ask_user.enabled', false);

    $user = createTestUser();
    copilotTestSwapFilament($user);

    $tools = app(ToolRegistry::class)->buildTools('admin', $user);

    expect(collect($tools)->contains(fn ($tool) => $tool instanceof AskUserTool))->toBeFalse();
});

it('tells the model to ask questions through ask_user', function () {
    $builder = app(ContextBuilder::class);
    $guidance = (fn () => $this->buildAskUserGuidance())->call($builder);

    expect($guidance)->toContain('ask_user')
        ->toContain('Do not ask questions in plain text');

    config()->set('filament-copilot.ask_user.enabled', false);

    expect((fn () => $this->buildAskUserGuidance())->call($builder))->toBe('');
});

it('uses the configured agent timeout for provider requests', function () {
    config()->set('filament-copilot.agent.timeout', 300);

    expect(app(CopilotAgent::class)->timeout())->toBe(300);

    config()->set('filament-copilot.agent.timeout', 0);

    expect(app(CopilotAgent::class)->timeout())->toBe(1);
});
