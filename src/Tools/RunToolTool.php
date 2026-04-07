<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunToolTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Execute a copilot tool from a resource, page, or widget. First use get_tools to discover available tools and their parameters, then use this tool to run them.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_class' => $schema->string()->description('The fully qualified class name of the resource, page, or widget that owns the tool')->required(),
            'tool_class' => $schema->string()->description('The fully qualified class name of the tool to execute')->required(),
            'arguments' => $schema->string()->description('JSON object of arguments to pass to the tool (e.g. {"page": 1, "per_page": 10})'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $sourceClass = (string) $request['source_class'];
        $toolClass = (string) $request['tool_class'];
        $argumentsJson = $request['arguments'] !== null ? (string) $request['arguments'] : '{}';

        // Validate source class
        if (! class_exists($sourceClass)) {
            return "Source class '{$sourceClass}' not found.";
        }

        $isCopilot = is_subclass_of($sourceClass, CopilotResource::class)
            || is_subclass_of($sourceClass, CopilotPage::class)
            || is_subclass_of($sourceClass, CopilotWidget::class);

        if (! $isCopilot) {
            return "Source class '{$sourceClass}' does not implement any Copilot interface.";
        }

        if (! $this->isSourceAuthorized($sourceClass)) {
            return "Access denied: you do not have permission to access '" . class_basename($sourceClass) . "'.";
        }

        // Get tools from source and verify the tool is registered
        try {
            $tools = $sourceClass::copilotTools();
        } catch (\Throwable $e) {
            return "Failed to load tools from '{$sourceClass}': " . $e->getMessage();
        }

        $targetTool = null;
        foreach ($tools as $tool) {
            if (get_class($tool) === $toolClass) {
                $targetTool = $tool;
                break;
            }
        }

        if (! $targetTool) {
            return "Tool '{$toolClass}' is not registered in '" . class_basename($sourceClass) . "'. Use get_tools to see available tools.";
        }

        // Set context on the tool if it's a BaseTool
        if ($targetTool instanceof BaseTool) {
            if (isset($this->panelId)) {
                $targetTool->forPanel($this->panelId);
            }
            if (isset($this->user)) {
                $targetTool->forUser($this->user);
            }
            $targetTool->forTenant($this->tenant);
            $targetTool->forConversation($this->conversationId);
        }

        // Parse and execute
        $args = json_decode($argumentsJson, true) ?? [];

        try {
            $toolRequest = new Request($args);
            $result = (string) $targetTool->handle($toolRequest);
        } catch (\Throwable $e) {
            return 'Tool execution failed: ' . $e->getMessage();
        }

        $this->audit(AuditAction::ToolExecuted, $sourceClass, null, [
            'tool_class' => $toolClass,
            'arguments' => $args,
        ]);

        return $result;
    }

    protected function isSourceAuthorized(string $sourceClass): bool
    {
        if (! FilamentCopilotPlugin::get()->shouldRespectAuthorization()) {
            return true;
        }

        try {
            if (is_subclass_of($sourceClass, CopilotWidget::class)) {
                if (! method_exists($sourceClass, 'canView')) {
                    return true;
                }

                return (bool) call_user_func([$sourceClass, 'canView']);
            }

            if (is_subclass_of($sourceClass, CopilotResource::class) || is_subclass_of($sourceClass, CopilotPage::class)) {
                if (! method_exists($sourceClass, 'canAccess')) {
                    return true;
                }

                return (bool) call_user_func([$sourceClass, 'canAccess']);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
