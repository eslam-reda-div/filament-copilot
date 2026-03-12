<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Commands;

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeCopilotToolCommand extends Command
{
    protected $signature = 'make:copilot-tool';

    protected $description = 'Generate a Copilot tool for a Filament resource, page, or widget';

    protected array $templates = [
        'list' => 'List records with pagination',
        'view' => 'View a single record by ID',
        'search' => 'Search records by keyword',
        'create' => 'Create a new record',
        'edit' => 'Edit/update an existing record',
        'delete' => 'Delete a record',
        'force-delete' => 'Permanently delete a record (force delete)',
        'restore' => 'Restore a soft-deleted record',
        'custom' => 'Custom tool (blank template)',
    ];

    public function handle(): int
    {
        $panelIds = array_keys(Filament::getPanels());

        if (empty($panelIds)) {
            $this->error('No Filament panels found.');

            return self::FAILURE;
        }

        // Step 1: Choose panel
        $panelId = select(
            label: 'Which panel?',
            options: $panelIds,
        );

        $panel = Filament::getPanel($panelId);

        // Step 2: Choose type
        $type = select(
            label: 'What type of tool?',
            options: [
                'resource' => 'Resource Tool',
                'page' => 'Page Tool',
                'widget' => 'Widget Tool',
            ],
        );

        // Step 3: Choose target
        $targetClass = $this->chooseTarget($panel, $type);

        if (! $targetClass) {
            $this->error("No copilot-enabled {$type}s found in panel '{$panelId}'.");

            return self::FAILURE;
        }

        // Step 4: Choose template
        $templateOptions = $type === 'resource'
            ? $this->templates
            : ['custom' => 'Custom tool (blank template)'];

        $template = select(
            label: 'Choose a template:',
            options: $templateOptions,
        );

        // Step 5: Tool class name
        $defaultName = $this->suggestToolName($targetClass, $template, $type);

        $toolName = text(
            label: 'Tool class name:',
            default: $defaultName,
            required: true,
        );

        // Step 6: Generate
        $outputPath = $this->generateTool($panelId, $type, $targetClass, $template, $toolName);

        $this->info("Copilot tool created: {$outputPath}");

        return self::SUCCESS;
    }

    protected function chooseTarget($panel, string $type): ?string
    {
        $options = [];

        if ($type === 'resource') {
            foreach ($panel->getResources() as $resourceClass) {
                if (is_subclass_of($resourceClass, CopilotResource::class)) {
                    $options[(string) $resourceClass] = class_basename($resourceClass);
                }
            }
        } elseif ($type === 'page') {
            foreach ($panel->getPages() as $pageClass) {
                if (is_subclass_of($pageClass, CopilotPage::class)) {
                    $options[(string) $pageClass] = class_basename($pageClass);
                }
            }
        } elseif ($type === 'widget') {
            foreach ($panel->getWidgets() as $widgetClass) {
                if (is_subclass_of($widgetClass, CopilotWidget::class)) {
                    $options[(string) $widgetClass] = class_basename($widgetClass);
                }
            }
        }

        if (empty($options)) {
            return null;
        }

        return select(
            label: "Which {$type}?",
            options: $options,
        );
    }

    protected function suggestToolName(string $targetClass, string $template, string $type): string
    {
        $baseName = class_basename($targetClass);

        if ($type === 'resource') {
            $baseName = Str::before($baseName, 'Resource');
        }

        return match ($template) {
            'list' => "List{$baseName}sTool",
            'view' => "View{$baseName}Tool",
            'search' => "Search{$baseName}sTool",
            'create' => "Create{$baseName}Tool",
            'edit' => "Edit{$baseName}Tool",
            'delete' => "Delete{$baseName}Tool",
            'force-delete' => "ForceDelete{$baseName}Tool",
            'restore' => "Restore{$baseName}Tool",
            default => "{$baseName}Tool",
        };
    }

    protected function generateTool(string $panelId, string $type, string $targetClass, string $template, string $toolName): string
    {
        $stubPath = __DIR__ . "/../../stubs/copilot-tool.{$template}.stub";

        if (! file_exists($stubPath)) {
            $stubPath = __DIR__ . '/../../stubs/copilot-tool.custom.stub';
        }

        $stub = file_get_contents($stubPath);

        // Determine output directory and namespace
        [$namespace, $directory] = $this->resolveTarget($panelId, $type, $targetClass);

        // Replacements
        $resourceShortName = class_basename($targetClass);
        $modelLabel = $type === 'resource'
            ? Str::before($resourceShortName, 'Resource')
            : $resourceShortName;
        $modelPluralLabel = Str::plural($modelLabel);

        $stub = str_replace([
            '{{ namespace }}',
            '{{ className }}',
            '{{ resourceClass }}',
            '{{ resourceShortName }}',
            '{{ modelLabel }}',
            '{{ modelPluralLabel }}',
        ], [
            $namespace,
            $toolName,
            $targetClass,
            $resourceShortName,
            $modelLabel,
            $modelPluralLabel,
        ], $stub);

        // Ensure directory exists
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . '/' . $toolName . '.php';
        file_put_contents($filePath, $stub);

        return $filePath;
    }

    protected function resolveTarget(string $panelId, string $type, string $targetClass): array
    {
        $baseName = class_basename($targetClass);

        if ($type === 'resource') {
            // Resource tools go inside: {ResourceClass}/CopilotTools/
            $resourceNamespace = Str::beforeLast($targetClass, '\\');
            $namespace = $resourceNamespace . '\\' . $baseName . '\\CopilotTools';

            $reflected = new \ReflectionClass($targetClass);
            $resourceDir = dirname($reflected->getFileName());
            $directory = $resourceDir . '/' . $baseName . '/CopilotTools';

            return [$namespace, $directory];
        }

        if ($type === 'page') {
            $pageNamespace = Str::beforeLast($targetClass, '\\');
            $namespace = $pageNamespace . '\\CopilotTools\\' . $baseName;

            $reflected = new \ReflectionClass($targetClass);
            $pageDir = dirname($reflected->getFileName());
            $directory = $pageDir . '/CopilotTools/' . $baseName;

            return [$namespace, $directory];
        }

        // widget
        $widgetNamespace = Str::beforeLast($targetClass, '\\');
        $namespace = $widgetNamespace . '\\CopilotTools\\' . $baseName;

        $reflected = new \ReflectionClass($targetClass);
        $widgetDir = dirname($reflected->getFileName());
        $directory = $widgetDir . '/CopilotTools/' . $baseName;

        return [$namespace, $directory];
    }
}
