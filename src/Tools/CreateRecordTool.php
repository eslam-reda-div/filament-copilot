<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateRecordTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Create a new record in a resource. Provide the resource slug and the field values as JSON.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'data' => $schema->string()->description('JSON object of field:value pairs to create the record with')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = (string) $request['resource'];
        $resourceClass = $this->resolveResource($resource);

        if (! $resourceClass) {
            return "Resource '{$resource}' not found.";
        }

        if (! $this->authorizeCreate($resourceClass)) {
            return 'You are not authorized to create records in this resource.';
        }

        $dataRaw = $request['data'];
        $data = is_string($dataRaw) ? json_decode($dataRaw, true) : $dataRaw;

        if (! is_array($data)) {
            return 'Invalid data format. Provide a JSON object of field:value pairs.';
        }

        $modelClass = $resourceClass::getModel();
        $model = new $modelClass;
        $fillable = $model->getFillable();

        // Only allow fillable fields
        $safeData = array_intersect_key($data, array_flip($fillable));

        if (empty($safeData)) {
            return 'No valid fields provided. Fillable fields are: '.implode(', ', $fillable);
        }

        $record = $modelClass::create($safeData);

        $this->audit(AuditAction::RecordCreated, $resourceClass, (string) $record->getKey(), [
            'data' => $safeData,
        ]);

        return "Created {$resourceClass::getModelLabel()} #{$record->getKey()} successfully.";
    }
}
