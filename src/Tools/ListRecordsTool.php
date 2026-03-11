<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListRecordsTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'List records from a resource table. Returns paginated results with key columns.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug (e.g. "users", "posts")')->required(),
            'page' => $schema->integer()->description('Page number, defaults to 1'),
            'per_page' => $schema->integer()->description('Records per page, defaults to 10, max 50'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = (string) $request['resource'];
        $resourceClass = $this->resolveResource($resource);

        if (! $resourceClass) {
            return "Resource '{$resource}' not found.";
        }

        if (! $this->authorizeViewAny($resourceClass)) {
            return 'You are not authorized to view records for this resource.';
        }

        $modelClass = $resourceClass::getModel();
        $perPage = min((int) ($request['per_page'] ?? 10), 50);
        $page = max((int) ($request['page'] ?? 1), 1);

        $query = $modelClass::query();

        if ($this->tenant && method_exists($modelClass, 'scopeForTenant')) {
            $query->forTenant($this->tenant);
        }

        $records = $query->paginate($perPage, ['*'], 'page', $page);

        $this->audit(AuditAction::RecordRead, $resourceClass, null, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $records->total(),
        ]);

        if ($records->isEmpty()) {
            return "No records found for {$resourceClass::getPluralModelLabel()}.";
        }

        $lines = [
            "{$resourceClass::getPluralModelLabel()} - Page {$records->currentPage()} of {$records->lastPage()} ({$records->total()} total)",
            '',
        ];

        foreach ($records as $record) {
            $lines[] = "- #{$record->getKey()}: ".$this->summarizeRecord($record);
        }

        return implode("\n", $lines);
    }

    protected function summarizeRecord($record): string
    {
        $attributes = $record->toArray();
        $summary = [];

        foreach (array_slice($attributes, 0, 5) as $key => $value) {
            if (is_array($value) || is_null($value)) {
                continue;
            }
            $display = is_string($value) ? mb_substr((string) $value, 0, 50) : $value;
            $summary[] = "{$key}: {$display}";
        }

        return implode(', ', $summary);
    }
}
