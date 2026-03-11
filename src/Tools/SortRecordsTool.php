<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SortRecordsTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Get records sorted by a specific column in ascending or descending order.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'column' => $schema->string()->description('The column to sort by')->required(),
            'direction' => $schema->string()->description('Sort direction: asc or desc. Defaults to asc.'),
            'limit' => $schema->integer()->description('Max results, defaults to 10'),
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
        $column = (string) $request['column'];
        $direction = in_array($request['direction'], ['asc', 'desc']) ? (string) $request['direction'] : 'asc';
        $limit = min((int) ($request['limit'] ?? 10), 50);

        $records = $modelClass::query()
            ->orderBy($column, $direction)
            ->limit($limit)
            ->get();

        $this->audit(AuditAction::RecordSorted, $resourceClass, null, [
            'column' => $column,
            'direction' => $direction,
        ]);

        if ($records->isEmpty()) {
            return 'No records found.';
        }

        $lines = ["{$resourceClass::getPluralModelLabel()} sorted by {$column} ({$direction}), showing {$records->count()}:", ''];

        foreach ($records as $record) {
            $key = $record->getKey();
            $attrs = collect($record->toArray())
                ->take(5)
                ->filter(fn ($v) => ! is_array($v) && ! is_null($v))
                ->map(fn ($v, $k) => "{$k}: {$v}")
                ->implode(', ');
            $lines[] = "- #{$key}: {$attrs}";
        }

        return implode("\n", $lines);
    }
}
