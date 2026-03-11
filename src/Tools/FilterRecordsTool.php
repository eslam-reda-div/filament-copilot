<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class FilterRecordsTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Filter records in a resource by field values. Supports exact match, greater than, less than operators.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'filters' => $schema->string()->description('JSON object of field:value pairs. Use {field: {op: "gt", value: 10}} for operators (gt, gte, lt, lte, like, not).')->required(),
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
            return 'You are not authorized to filter records for this resource.';
        }

        $modelClass = $resourceClass::getModel();
        $filtersRaw = $request['filters'];
        $filters = is_string($filtersRaw) ? json_decode($filtersRaw, true) : $filtersRaw;

        if (! is_array($filters)) {
            return 'Invalid filters format. Provide a JSON object of field:value pairs.';
        }

        $limit = min((int) ($request['limit'] ?? 10), 50);
        $query = $modelClass::query();

        foreach ($filters as $field => $condition) {
            if (is_array($condition) && isset($condition['op'], $condition['value'])) {
                $operator = match ($condition['op']) {
                    'gt' => '>',
                    'gte' => '>=',
                    'lt' => '<',
                    'lte' => '<=',
                    'like' => 'LIKE',
                    'not' => '!=',
                    default => '=',
                };
                $value = $condition['op'] === 'like' ? "%{$condition['value']}%" : $condition['value'];
                $query->where($field, $operator, $value);
            } else {
                $query->where($field, $condition);
            }
        }

        $records = $query->limit($limit)->get();

        $this->audit(AuditAction::RecordFiltered, $resourceClass, null, [
            'filters' => $filters,
            'results_count' => $records->count(),
        ]);

        if ($records->isEmpty()) {
            return 'No records match the given filters.';
        }

        $lines = ["Filtered {$resourceClass::getPluralModelLabel()} ({$records->count()} found):", ''];

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
