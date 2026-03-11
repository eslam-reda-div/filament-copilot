<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateRecordTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Update an existing record in a resource. Provide the resource slug, record ID, and field values to update.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'id' => $schema->string()->description('The record ID to update')->required(),
            'data' => $schema->string()->description('JSON object of field:value pairs to update')->required(),
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

        if (! $this->authorizeEdit($resourceClass, $record)) {
            return 'You are not authorized to edit this record.';
        }

        $dataRaw = $request['data'];
        $data = is_string($dataRaw) ? json_decode($dataRaw, true) : $dataRaw;

        if (! is_array($data)) {
            return 'Invalid data format. Provide a JSON object of field:value pairs.';
        }

        $fillable = $record->getFillable();
        $safeData = array_intersect_key($data, array_flip($fillable));

        if (empty($safeData)) {
            return 'No valid fields provided. Fillable fields are: '.implode(', ', $fillable);
        }

        $record->update($safeData);

        $this->audit(AuditAction::RecordUpdated, $resourceClass, (string) $record->getKey(), [
            'data' => $safeData,
        ]);

        return "Updated {$resourceClass::getModelLabel()} #{$record->getKey()} successfully.";
    }
}
