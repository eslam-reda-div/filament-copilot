<?php

use EslamRedaDiv\FilamentCopilot\Tools\GetToolsTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListPagesTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListResourcesTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListWidgetsTool;
use EslamRedaDiv\FilamentCopilot\Tools\RunToolTool;
use Laravel\Ai\Contracts\Tool;

it('GetToolsTool implements Tool contract', function () {
    $tool = new GetToolsTool;
    expect($tool)->toBeInstanceOf(Tool::class);
});

it('GetToolsTool has proper description', function () {
    $tool = new GetToolsTool;
    $description = (string) $tool->description();
    expect($description)->toContain('tool');
});

it('GetToolsTool has schema with source_class', function () {
    $tool = new GetToolsTool;
    expect(method_exists($tool, 'schema'))->toBeTrue();
    $reflection = new ReflectionMethod($tool, 'schema');
    expect($reflection->getNumberOfParameters())->toBe(1);
});

it('RunToolTool implements Tool contract', function () {
    $tool = new RunToolTool;
    expect($tool)->toBeInstanceOf(Tool::class);
});

it('RunToolTool has proper description', function () {
    $tool = new RunToolTool;
    $description = (string) $tool->description();
    expect($description)->toContain('tool');
});

it('RunToolTool has schema with source_class and tool_class', function () {
    $tool = new RunToolTool;
    expect(method_exists($tool, 'schema'))->toBeTrue();
    $reflection = new ReflectionMethod($tool, 'schema');
    expect($reflection->getNumberOfParameters())->toBe(1);
});

it('ListPagesTool implements Tool contract', function () {
    $tool = app(ListPagesTool::class);
    expect($tool)->toBeInstanceOf(Tool::class);
});

it('ListPagesTool has proper description', function () {
    $tool = app(ListPagesTool::class);
    $description = (string) $tool->description();
    expect($description)->toContain('page')
        ->and($description)->toContain('panel');
});

it('ListWidgetsTool implements Tool contract', function () {
    $tool = app(ListWidgetsTool::class);
    expect($tool)->toBeInstanceOf(Tool::class);
});

it('ListWidgetsTool has proper description', function () {
    $tool = app(ListWidgetsTool::class);
    $description = (string) $tool->description();
    expect($description)->toContain('widget')
        ->and($description)->toContain('panel');
});

it('ListResourcesTool implements Tool contract', function () {
    $tool = app(ListResourcesTool::class);
    expect($tool)->toBeInstanceOf(Tool::class);
});

it('ListResourcesTool has proper description', function () {
    $tool = app(ListResourcesTool::class);
    $description = (string) $tool->description();
    expect($description)->toContain('resource')
        ->and($description)->toContain('panel');
});