<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchRecordsTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Search records in a resource by a search term across searchable columns.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'query' => $schema->string()->description('The search term')->required(),
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
            return 'You are not authorized to search records for this resource.';
        }

        $modelClass = $resourceClass::getModel();
        $searchTerm = (string) $request['query'];
        $limit = min((int) ($request['limit'] ?? 10), 50);

        $query = $modelClass::query();

        // Use model's searchable columns if available
        $fillable = (new $modelClass)->getFillable();
        $stringColumns = array_filter($fillable, fn ($col) => ! str_ends_with($col, '_id') && ! in_array($col, ['password', 'remember_token']));

        if (! empty($stringColumns)) {
            $query->where(function ($q) use ($stringColumns, $searchTerm) {
                foreach ($stringColumns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        $records = $query->limit($limit)->get();

        $this->audit(AuditAction::RecordSearched, $resourceClass, null, [
            'query' => $searchTerm,
            'results_count' => $records->count(),
        ]);

        if ($records->isEmpty()) {
            return "No records found matching '{$searchTerm}'.";
        }

        $lines = ["Search results for '{$searchTerm}' in {$resourceClass::getPluralModelLabel()} ({$records->count()} found):", ''];

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
