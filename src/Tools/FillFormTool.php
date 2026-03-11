<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class FillFormTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Fill form fields for a new or existing record. Returns the proposed data for user confirmation before saving.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug')->required(),
            'data' => $schema->string()->description('JSON object of field:value pairs to fill in the form')->required(),
            'record_id' => $schema->string()->description('Optional record ID for edit forms. Omit for create forms.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = (string) $request['resource'];
        $resourceClass = $this->resolveResource($resource);

        if (! $resourceClass) {
            return "Resource '{$resource}' not found.";
        }

        $dataRaw = $request['data'];
        $data = is_string($dataRaw) ? json_decode($dataRaw, true) : $dataRaw;

        if (! is_array($data)) {
            return 'Invalid data format. Provide a JSON object of field:value pairs.';
        }

        $recordId = $request['record_id'] !== null ? (string) $request['record_id'] : null;
        $mode = $recordId ? 'edit' : 'create';

        $this->audit(AuditAction::FormFilled, $resourceClass, $recordId, [
            'mode' => $mode,
            'fields' => array_keys($data),
        ]);

        $lines = ["Form data prepared for {$mode} on {$resourceClass::getModelLabel()}:", ''];

        foreach ($data as $field => $value) {
            $display = is_array($value) ? json_encode($value) : $value;
            $lines[] = "  {$field}: {$display}";
        }

        $lines[] = '';
        $lines[] = 'Please confirm to save this data, or ask me to modify specific fields.';

        return implode("\n", $lines);
    }
}
