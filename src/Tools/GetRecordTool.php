<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetRecordTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Get a single record by its ID from a resource.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'id' => $schema->string()->description('The record ID')->required(),
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
        $id = (string) $request['id'];
        $record = $modelClass::find($id);

        if (! $record) {
            return "Record #{$id} not found.";
        }

        if (! $this->authorizeView($resourceClass, $record)) {
            return 'You are not authorized to view this record.';
        }

        $this->audit(AuditAction::RecordRead, $resourceClass, (string) $record->getKey());

        $attributes = $record->toArray();
        $lines = ["{$resourceClass::getModelLabel()} #{$record->getKey()}:", ''];

        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $lines[] = "  {$key}: {$value}";
        }

        return implode("\n", $lines);
    }
}
