<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use Illuminate\Support\Str;

/*
 * Issue #39: the text of consecutive agent steps was concatenated without
 * a separator, gluing sentences across a tool call and breaking Markdown
 * blocks (tables, lists, headings) that start the next step.
 */

function streamedReply(array $events): array
{
    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([copilotTestStream($events)]);

    $sse = copilotTestParseSse(copilotTestRunStream(['message' => 'Show me the projects']));

    $stored = CopilotMessage::query()->where('role', MessageRole::Assistant->value)->sole()->content;
    $streamed = collect($sse)->where(0, 'chunk')->map(fn (array $event) => $event[1]['text'])->implode('');

    return [$stored, $streamed];
}

function renderAssistant(string $content): string
{
    return view('filament-copilot::components.chat-message', ['msg' => [
        'role' => 'assistant',
        'content' => $content,
    ]])->render();
}

it('starts the text after a tool call in a new paragraph', function () {
    [$stored, $streamed] = streamedReply([
        copilotTestTextDelta('Let me look up the details of the project.'),
        copilotTestToolCall('call-1', 'list_projects', [], 1),
        copilotTestToolResult('call-1', 'list_projects', '[...]', offset: 2),
        copilotTestTextDelta('Now I fetch the scenes.', 3),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe("Let me look up the details of the project.\n\nNow I fetch the scenes.")
        // The live stream gets the same separator, so it matches what is stored.
        ->and($streamed)->toBe($stored);
});

it('renders a markdown table that follows a tool call', function () {
    [$stored] = streamedReply([
        copilotTestTextDelta('Here are the projects.'),
        copilotTestToolCall('call-1', 'list_projects', [], 1),
        copilotTestToolResult('call-1', 'list_projects', '[...]', offset: 2),
        copilotTestTextDelta("| # | Title |\n", 3),
        copilotTestTextDelta("|---|---|\n| 1 | Alpha |", 4),
        copilotTestStreamEnd(),
    ]);

    expect(Str::markdown($stored))->toContain('<table>')
        ->and(renderAssistant($stored))->toContain('<table>')->toContain('<td>Alpha</td>');
});

it('separates every step of a multi step turn', function () {
    [$stored] = streamedReply([
        copilotTestTextDelta('Step one.'),
        copilotTestToolCall('call-1', 'a', [], 1),
        copilotTestToolResult('call-1', 'a', 'ok', offset: 2),
        copilotTestTextDelta('Step two.', 3),
        copilotTestToolCall('call-2', 'b', [], 4),
        copilotTestToolResult('call-2', 'b', 'ok', offset: 5),
        copilotTestTextDelta('Step three.', 6),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe("Step one.\n\nStep two.\n\nStep three.");
});

it('does not split text streamed within a single step', function () {
    [$stored] = streamedReply([
        copilotTestTextDelta('Hel'),
        copilotTestTextDelta('lo, wor', 1),
        copilotTestTextDelta('ld.', 2),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe('Hello, world.');
});

it('only adds the line breaks that are missing', function (string $before, string $after, string $expected) {
    [$stored] = streamedReply([
        copilotTestTextDelta($before),
        copilotTestToolCall('call-1', 'a', [], 1),
        copilotTestToolResult('call-1', 'a', 'ok', offset: 2),
        copilotTestTextDelta($after, 3),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe($expected);
})->with([
    'previous step ended with a blank line' => ["One.\n\n", 'Two.', "One.\n\nTwo."],
    'previous step ended with a newline' => ["One.\n", 'Two.', "One.\n\nTwo."],
    'next step starts with a newline' => ['One.', "\nTwo.", "One.\n\nTwo."],
    'next step starts with a blank line' => ['One.', "\n\nTwo.", "One.\n\nTwo."],
]);

it('adds no leading break when the turn starts with a tool call', function () {
    [$stored, $streamed] = streamedReply([
        copilotTestToolCall('call-1', 'list_projects', [], 0),
        copilotTestToolResult('call-1', 'list_projects', '[...]', offset: 1),
        copilotTestTextDelta('There are three projects.', 2),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe('There are three projects.')
        ->and($streamed)->toBe('There are three projects.');
});

it('waits for the first non empty text after a boundary', function () {
    [$stored] = streamedReply([
        copilotTestTextDelta('One.'),
        copilotTestToolCall('call-1', 'a', [], 1),
        copilotTestToolResult('call-1', 'a', 'ok', offset: 2),
        copilotTestTextDelta('', 3),
        copilotTestTextDelta('Two.', 4),
        copilotTestStreamEnd(),
    ]);

    expect($stored)->toBe("One.\n\nTwo.");
});

it('repairs a table glued to the previous sentence in replies stored before the fix', function () {
    $legacy = "Hier sind die Szenen des Projekts.| # | Titel |\n|---|---|\n| 1 | Intro |";

    expect(renderAssistant($legacy))->toContain('<table>')
        ->toContain('<td>Intro</td>')
        ->toContain('Hier sind die Szenen des Projekts.');
});

it('leaves valid tables and pipes in prose untouched', function () {
    $table = "Name | Age |\n---|---|\nAda | 36 |";

    expect(renderAssistant($table))->toContain('<td>Ada</td>');

    $prose = 'Use a | b to pipe output. Then run it.';
    expect(renderAssistant($prose))->toContain('Use a | b to pipe output.')
        ->not->toContain('<table>');
});
