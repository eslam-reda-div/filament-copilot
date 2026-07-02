<?php

use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;

it('builds default tools', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);
    $tools = $registry->buildTools('admin', $user);
    // Should have the 7 built-in global tools
    expect($tools)->toBeArray()
        ->and(count($tools))->toBe(7);
});

it('accepts global custom tools', function () {
    $registry = app(ToolRegistry::class);
    $registry->registerGlobal('App\\Tools\\CustomTool');
    expect(count($registry->getToolClasses()))->toBe(8);
});

it('accepts tool instances', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);

    $instance = new class
    {
        public string $name = 'runtime_tool';
    };

    $registry->registerGlobal($instance);
    $tools = $registry->buildTools('admin', $user);

    expect(count($tools))->toBe(8)
        ->and($tools)->toContain($instance);
});

it('accepts closures returning a single tool', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);

    $instance = new class
    {
        public string $name = 'lazy_tool';
    };

    $registry->registerGlobal(fn () => $instance);
    $tools = $registry->buildTools('admin', $user);

    expect(count($tools))->toBe(8)
        ->and($tools)->toContain($instance);
});

it('accepts closures returning an iterable of tools', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);

    $first = new class
    {
        public string $name = 'mcp_tool_one';
    };
    $second = new class
    {
        public string $name = 'mcp_tool_two';
    };

    $registry->registerGlobal(fn () => [$first, $second]);
    $tools = $registry->buildTools('admin', $user);

    expect(count($tools))->toBe(9)
        ->and($tools)->toContain($first)
        ->and($tools)->toContain($second);
});

it('skips closures resolving to null', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);

    $registry->registerGlobal(fn () => null);
    $tools = $registry->buildTools('admin', $user);

    expect(count($tools))->toBe(7);
});

it('re-invokes closures on every build', function () {
    $user = createTestUser();
    $registry = app(ToolRegistry::class);

    $calls = 0;
    $registry->registerGlobal(function () use (&$calls) {
        $calls++;

        return null;
    });

    $registry->buildTools('admin', $user);
    $registry->buildTools('admin', $user);

    expect($calls)->toBe(2);
});