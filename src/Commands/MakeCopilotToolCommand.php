<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Commands;

use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCopilotToolCommand extends Command
{
    protected $signature = 'make:copilot-tool';

    protected $description = 'Generate a Copilot tool for a Filament resource, page, or widget';

    protected array $resourceTemplates = [
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

    protected array $simpleTemplates = [
        'custom' => 'Custom tool (blank template)',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->info('🛠  Filament Copilot — Tool Generator');
        $this->line('This wizard will guide you through creating a Copilot tool.');
        $this->newLine();

        // ── Step 1: Choose panel ──────────────────────────────────────

        $this->section('Step 1/5 — Select panel');

        $panelIds = array_keys(Filament::getPanels());

        if (empty($panelIds)) {
            $this->error('No Filament panels found. Register at least one panel first.');

            return self::FAILURE;
        }

        $this->line('Available panels:');
        $this->newLine();

        foreach ($panelIds as $id) {
            $this->line("  • {$id}");
        }

        $this->newLine();

        $panelId = $this->choice(
            'Which panel should this tool belong to?',
            $panelIds,
            $panelIds[0],
        );

        $panel = Filament::getPanel($panelId);

        $this->info("✓ Panel: {$panelId}");

        // ── Step 2: Choose type (resource / page / widget) ───────────

        $this->section('Step 2/5 — Select tool type');

        $typeLabels = [
            'Resource Tool',
            'Page Tool',
            'Widget Tool',
        ];

        $typeKeys = ['resource', 'page', 'widget'];

        $typeChoice = $this->choice(
            'What type of tool do you want to create?',
            $typeLabels,
            $typeLabels[0],
        );

        $type = $typeKeys[array_search($typeChoice, $typeLabels)];

        $this->info("✓ Type: {$typeChoice}");

        // ── Step 3: Choose target ────────────────────────────────────

        $this->section('Step 3/5 — Select target');

        $targets = $this->getTargets($panel, $type);

        if (empty($targets)) {
            $this->newLine();
            $this->error("No copilot-enabled {$type}s found in the '{$panelId}' panel.");
            $this->newLine();
            $this->line('Make sure your ' . match ($type) {
                'resource' => 'resource implements the CopilotResource interface.',
                'page' => 'page implements the CopilotPage interface.',
                'widget' => 'widget implements the CopilotWidget interface.',
            });

            return self::FAILURE;
        }

        $targetLabels = array_values($targets);
        $targetClasses = array_keys($targets);

        $this->line("Available copilot-enabled {$type}s in '{$panelId}':");
        $this->newLine();

        foreach ($targetLabels as $label) {
            $this->line("  • {$label}");
        }

        $this->newLine();

        $selectedLabel = $this->choice(
            "Which {$type}?",
            $targetLabels,
            $targetLabels[0],
        );

        $selectedIndex = array_search($selectedLabel, $targetLabels);
        $targetClass = $targetClasses[$selectedIndex];

        $this->info("✓ Target: {$selectedLabel}");

        // ── Step 4: Choose template ──────────────────────────────────

        $this->section('Step 4/5 — Select template');

        $templates = $type === 'resource'
            ? $this->resourceTemplates
            : $this->simpleTemplates;

        $templateLabels = [];
        $templateKeys = [];

        foreach ($templates as $key => $description) {
            $templateLabels[] = "{$key} — {$description}";
            $templateKeys[] = $key;
        }

        $this->line('Available templates:');
        $this->newLine();

        foreach ($templateLabels as $label) {
            $this->line("  • {$label}");
        }

        $this->newLine();

        $templateChoice = $this->choice(
            'Choose a template',
            $templateLabels,
            $templateLabels[0],
        );

        $template = $templateKeys[array_search($templateChoice, $templateLabels)];

        $this->info("✓ Template: {$template}");

        // ── Step 5: Tool class name ──────────────────────────────────

        $this->section('Step 5/5 — Tool class name');

        $defaultName = $this->suggestToolName($targetClass, $template, $type);

        $toolName = $this->ask(
            'Enter the tool class name',
            $defaultName,
        );

        if (empty($toolName)) {
            $toolName = $defaultName;
        }

        $toolName = Str::studly($toolName);

        $this->info("✓ Class name: {$toolName}");

        // ── Generate ─────────────────────────────────────────────────

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->newLine();

        $this->line('⏳ Generating tool...');

        $outputPath = $this->generateTool($type, $targetClass, $template, $toolName);

        $this->newLine();
        $this->info('🎉 Copilot tool created successfully!');
        $this->newLine();

        $relativePath = Str::after($outputPath, base_path() . DIRECTORY_SEPARATOR);

        $this->table(
            ['Setting', 'Value'],
            [
                ['Panel', $panelId],
                ['Type', $typeChoice],
                ['Target', class_basename($targetClass)],
                ['Template', $template],
                ['Class', $toolName],
                ['Path', $relativePath],
            ],
        );

        $this->newLine();
        $this->line('Don\'t forget to register the tool in your ' . match ($type) {
            'resource' => 'resource\'s copilotTools() method.',
            'page' => 'page\'s copilotTools() method.',
            'widget' => 'widget\'s copilotTools() method.',
        });

        return self::SUCCESS;
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->info($title);
        $this->line(str_repeat('─', 60));
        $this->newLine();
    }

    /**
     * @return array<class-string, string>
     */
    protected function getTargets($panel, string $type): array
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

        return $options;
    }

    protected function suggestToolName(string $targetClass, string $template, string $type): string
    {
        $baseName = class_basename($targetClass);

        if ($type === 'resource') {
            $baseName = Str::before($baseName, 'Resource');
        }

        $singular = Str::studly($baseName);
        $plural = Str::pluralStudly($baseName);

        return match ($template) {
            'list' => "List{$plural}Tool",
            'view' => "View{$singular}Tool",
            'search' => "Search{$plural}Tool",
            'create' => "Create{$singular}Tool",
            'edit' => "Edit{$singular}Tool",
            'delete' => "Delete{$singular}Tool",
            'force-delete' => "ForceDelete{$singular}Tool",
            'restore' => "Restore{$singular}Tool",
            default => "{$singular}Tool",
        };
    }

    protected function generateTool(string $type, string $targetClass, string $template, string $toolName): string
    {
        $stubPath = __DIR__ . "/../../stubs/copilot-tool.{$template}.stub";

        if (! file_exists($stubPath)) {
            $stubPath = __DIR__ . '/../../stubs/copilot-tool.custom.stub';
        }

        $stub = file_get_contents($stubPath);

        // Determine output directory and namespace
        [$namespace, $directory] = $this->resolveTarget($type, $targetClass);

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

    protected function resolveTarget(string $type, string $targetClass): array
    {
        $baseName = class_basename($targetClass);

        if ($type === 'resource') {
            // Resource tools: {ResourceDir}/{ResourceBaseName}/CopilotTools/
            $resourceNamespace = Str::beforeLast($targetClass, '\\');
            $namespace = $resourceNamespace . '\\' . $baseName . '\\CopilotTools';

            $reflected = new \ReflectionClass($targetClass);
            $resourceDir = dirname((string) $reflected->getFileName());
            $directory = $resourceDir . '/' . $baseName . '/CopilotTools';

            return [$namespace, $directory];
        }

        if ($type === 'page') {
            // Page tools: {PagesDir}/CopilotTools/{PageBaseName}/
            $pageNamespace = Str::beforeLast($targetClass, '\\');
            $namespace = $pageNamespace . '\\CopilotTools\\' . $baseName;

            $reflected = new \ReflectionClass($targetClass);
            $pageDir = dirname((string) $reflected->getFileName());
            $directory = $pageDir . '/CopilotTools/' . $baseName;

            return [$namespace, $directory];
        }

        // Widget tools: {WidgetsDir}/CopilotTools/{WidgetBaseName}/
        $widgetNamespace = Str::beforeLast($targetClass, '\\');
        $namespace = $widgetNamespace . '\\CopilotTools\\' . $baseName;

        $reflected = new \ReflectionClass($targetClass);
        $widgetDir = dirname((string) $reflected->getFileName());
        $directory = $widgetDir . '/CopilotTools/' . $baseName;

        return [$namespace, $directory];
    }
}
