<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Services;

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
     */
    public function registerGlobal(string $toolClass): void
    {
        $this->globalTools[] = $toolClass;
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

        foreach (array_merge($this->toolClasses, $this->globalTools, $pluginTools) as $toolClass) {
            $tool = app($toolClass);

            if ($tool instanceof BaseTool) {
                $tool->forPanel($panelId)
                    ->forUser($user)
                    ->forTenant($tenant);
            }

            if ($conversationId && $tool instanceof BaseTool) {
                $tool->forConversation($conversationId);
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Get the list of registered tool classes.
     */
    public function getToolClasses(): array
    {
        return array_merge($this->toolClasses, $this->globalTools);
    }
}
