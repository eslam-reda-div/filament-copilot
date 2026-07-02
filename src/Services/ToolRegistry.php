<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Services;

use Closure;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use EslamRedaDiv\FilamentCopilot\Tools\GetToolsTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListPagesTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListResourcesTool;
use EslamRedaDiv\FilamentCopilot\Tools\ListWidgetsTool;
use EslamRedaDiv\FilamentCopilot\Tools\RecallTool;
use EslamRedaDiv\FilamentCopilot\Tools\RememberTool;
use EslamRedaDiv\FilamentCopilot\Tools\RunToolTool;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class ToolRegistry
{
    protected array $globalTools = [];

    protected array $toolClasses = [
        // Discovery
        ListResourcesTool::class,
        ListPagesTool::class,
        ListWidgetsTool::class,
        GetToolsTool::class,
        RunToolTool::class,
        // Memory
        RememberTool::class,
        RecallTool::class,
    ];

    /**
     * Register a global custom tool.
     *
     * Accepts a class-string (resolved via the container), a tool instance,
     * or a closure. Closures are invoked on every buildTools() call and may
     * return a single tool or an iterable of tools — this is how runtime-only
     * tools (e.g. laravel/ai ^0.8 MCP client tools, which are instances
     * produced by an MCP connection rather than resolvable classes) can be
     * registered. Memoize inside the closure if resolution is expensive.
     */
    public function registerGlobal(string|object $tool): void
    {
        $this->globalTools[] = $tool;
    }

    /**
     * Build all tools configured for a panel/user context.
     */
    public function buildTools(string $panelId, Model $user, ?Model $tenant = null, ?string $conversationId = null): array
    {
        // Merge plugin-configured global tools
        $pluginTools = [];
        try {
            $plugin = \EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin::get();
            $pluginTools = $plugin->getGlobalTools();
        } catch (\Throwable) {
            $pluginTools = config('filament-copilot.global_tools', []);
        }

        $tools = [];

        foreach (array_merge($this->toolClasses, $this->globalTools, $pluginTools) as $entry) {
            foreach ($this->resolveTools($entry) as $tool) {
                if ($tool instanceof BaseTool) {
                    $tool->forPanel($panelId)
                        ->forUser($user)
                        ->forTenant($tenant);

                    if ($conversationId) {
                        $tool->forConversation($conversationId);
                    }
                }

                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Resolve a registered entry into zero or more tool objects.
     */
    protected function resolveTools(string|object $entry): iterable
    {
        if (is_string($entry)) {
            $entry = app($entry);
        }

        if ($entry instanceof Closure) {
            $entry = $entry();
        }

        if ($entry === null) {
            return;
        }

        if (is_iterable($entry)) {
            foreach ($entry as $tool) {
                if ($tool !== null) {
                    yield $tool;
                }
            }

            return;
        }

        yield $entry;
    }

    /**
     * Get the list of registered tool entries (class-strings, instances, or closures).
     */
    public function getToolClasses(): array
    {
        return array_merge($this->toolClasses, $this->globalTools);
    }
}
