<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class NavigateToPageTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Navigate the user to a specific page or resource in the panel. Returns the URL for the frontend to navigate to.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->string()->description('The page slug or resource slug to navigate to')->required(),
            'record_id' => $schema->string()->description('Optional record ID for resource detail/edit pages'),
            'action' => $schema->string()->description('Navigation action: list, create, view, edit. Defaults to list.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $page = (string) $request['page'];
        $recordId = $request['record_id'] !== null ? (string) $request['record_id'] : null;
        $action = (string) ($request['action'] ?? 'list');

        if (config('filament-copilot.audit.log_navigation', false)) {
            $this->audit(AuditAction::NavigatedTo, null, $recordId, [
                'page' => $page,
                'action' => $action,
            ]);
        }

        // Try to resolve as resource first
        $resourceClass = $this->resolveResource($page);

        if ($resourceClass) {
            $url = match ($action) {
                'create' => $resourceClass::getUrl('create'),
                'view' => $recordId ? $resourceClass::getUrl('view', ['record' => $recordId]) : $resourceClass::getUrl(),
                'edit' => $recordId ? $resourceClass::getUrl('edit', ['record' => $recordId]) : $resourceClass::getUrl(),
                default => $resourceClass::getUrl(),
            };

            return "Navigate to: {$url}";
        }

        // Try as a page
        $panel = Filament::getCurrentPanel();
        if ($panel) {
            foreach ($panel->getPages() as $pageClass) {
                if ($pageClass::getSlug() === $page) {
                    return "Navigate to: {$pageClass::getUrl()}";
                }
            }
        }

        return "Page or resource '{$page}' not found.";
    }
}
