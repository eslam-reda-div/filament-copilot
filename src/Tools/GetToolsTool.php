<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetToolsTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Get the list of available copilot tools for a specific resource, page, or widget. Returns each tool\'s name, description, and input parameters so you know how to call them via run_tool.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_class' => $schema->string()->description('The fully qualified class name of the resource, page, or widget to get tools for')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $sourceClass = (string) $request['source_class'];

        if (! class_exists($sourceClass)) {
            return "Class '{$sourceClass}' not found.";
        }

        $isCopilot = is_subclass_of($sourceClass, CopilotResource::class)
            || is_subclass_of($sourceClass, CopilotPage::class)
            || is_subclass_of($sourceClass, CopilotWidget::class);

        if (! $isCopilot) {
            return "Class '{$sourceClass}' does not implement any Copilot interface.";
        }

        if (! $this->isSourceAuthorized($sourceClass)) {
            return "Access denied: you do not have permission to access '" . class_basename($sourceClass) . "'.";
        }

        try {
            $tools = $sourceClass::copilotTools();
        } catch (\Throwable $e) {
            return "Failed to get tools from '{$sourceClass}': " . $e->getMessage();
        }

        if (empty($tools)) {
            return "No copilot tools available for '" . class_basename($sourceClass) . "'.";
        }

        $lines = ['Tools for ' . class_basename($sourceClass) . ':', ''];

        foreach ($tools as $tool) {
            $lines[] = '## ' . get_class($tool);
            $lines[] = 'Description: ' . $tool->description();

            try {
                $factory = new JsonSchemaTypeFactory;
                $params = $tool->schema($factory);

                if (! empty($params)) {
                    $objectType = (new ObjectType($params))->withoutAdditionalProperties();
                    $serialized = $objectType->toArray();
                    $properties = $serialized['properties'] ?? [];
                    $requiredFields = $serialized['required'] ?? [];

                    $lines[] = 'Parameters:';
                    foreach ($properties as $name => $prop) {
                        $type = is_array($prop['type'] ?? null) ? implode('|', $prop['type']) : ($prop['type'] ?? 'string');
                        $desc = $prop['description'] ?? '';
                        $isRequired = in_array($name, $requiredFields, true) ? ' (required)' : '';
                        $lines[] = "  - {$name} ({$type}){$isRequired}: {$desc}";
                    }
                } else {
                    $lines[] = 'Parameters: none';
                }
            } catch (\Throwable) {
                $lines[] = 'Parameters: (could not inspect)';
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
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
