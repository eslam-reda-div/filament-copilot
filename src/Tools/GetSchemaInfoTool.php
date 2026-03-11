<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Discovery\SchemaInspector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSchemaInfoTool extends BaseTool
{
    public function __construct(
        protected SchemaInspector $schemaInspector,
    ) {}

    public function description(): Stringable|string
    {
        return 'Get the database schema and model information for a resource. Useful to understand what fields are available.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->description('The resource slug to inspect')->required(),
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

        return $this->schemaInspector->describeForAi($modelClass);
    }
}
