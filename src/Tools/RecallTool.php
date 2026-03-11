<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Models\CopilotAgentMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecallTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Recall a previously stored memory about the user. Can recall a specific key or list all memories.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The memory key to recall. Leave empty to list all memories.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! config('filament-copilot.memory.enabled', true)) {
            return 'Memory feature is disabled.';
        }

        $key = $request['key'] !== null ? (string) $request['key'] : null;

        if ($key) {
            $value = CopilotAgentMemory::recall(
                participant: $this->user,
                panelId: $this->panelId,
                key: $key,
                tenant: $this->tenant,
            );

            if ($value === null) {
                return "No memory found for key '{$key}'.";
            }

            return "{$key}: {$value}";
        }

        $memories = CopilotAgentMemory::recallAll(
            participant: $this->user,
            panelId: $this->panelId,
            tenant: $this->tenant,
        );

        if (empty($memories)) {
            return 'No memories stored for this user.';
        }

        $lines = ['Stored memories:'];
        foreach ($memories as $k => $v) {
            $lines[] = "  - {$k}: {$v}";
        }

        return implode("\n", $lines);
    }
}
