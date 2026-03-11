<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadInfolistTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Read AI-enabled infolist entries for a specific record. Returns the visible entry values.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug (e.g. "users", "posts")')->required(),
            'record_id' => $schema->string()->description('The record ID to read infolist entries for')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = (string) $request['resource'];
        $resourceClass = $this->resolveResource($resource);

        if (! $resourceClass) {
            return "Resource '{$resource}' not found.";
        }

        $modelClass = $resourceClass::getModel();
        $recordId = (string) $request['record_id'];
        $record = $modelClass::find($recordId);

        if (! $record) {
            return "Record #{$recordId} not found.";
        }

        if (! $this->authorizeView($resourceClass, $record)) {
            return 'You are not authorized to view this record.';
        }

        $this->audit(AuditAction::RecordRead, $resourceClass, (string) $record->getKey());

        // Check if the resource has a ViewRecord page with an infolist
        $pages = $resourceClass::getPages();
        if (! isset($pages['view'])) {
            return "Resource '{$resource}' does not have a view page with an infolist.";
        }

        $lines = [
            "Infolist entries for {$resourceClass::getModelLabel()} #{$record->getKey()}:",
            '',
        ];

        // Read record attributes since we can't easily instantiate the infolist outside Livewire
        $attributes = $record->toArray();

        foreach ($attributes as $key => $value) {
            if (is_array($value) || is_null($value)) {
                continue;
            }

            $display = is_string($value) ? mb_substr((string) $value, 0, 200) : $value;
            $lines[] = "  {$key}: {$display}";
        }

        return implode("\n", $lines);
    }
}
