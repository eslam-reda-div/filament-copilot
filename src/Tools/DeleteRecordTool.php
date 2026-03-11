<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteRecordTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Delete a record from a resource. Requires the resource slug and record ID.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'id' => $schema->string()->description('The record ID to delete')->required(),
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

        if (! $this->authorizeDelete($resourceClass, $record)) {
            return 'You are not authorized to delete this record.';
        }

        $recordKey = (string) $record->getKey();
        $record->delete();

        $this->audit(AuditAction::RecordDeleted, $resourceClass, $recordKey);

        return "Deleted {$resourceClass::getModelLabel()} #{$recordKey} successfully.";
    }
}
