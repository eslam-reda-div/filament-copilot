<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Models\CopilotAgentMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberTool extends BaseTool
{
    public function description(): Stringable|string
    {
        return 'Remember a piece of information about the user for future conversations. Use this to store preferences, notes, or context.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('A short key/label for the memory (e.g. "preferred_language", "timezone")')->required(),
            'value' => $schema->string()->description('The value to remember')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! config('filament-copilot.memory.enabled', true)) {
            return 'Memory feature is disabled.';
        }

        $key = (string) $request['key'];
        $value = (string) $request['value'];

        // Check memory limit
        $currentCount = CopilotAgentMemory::query()
            ->forParticipant($this->user)
            ->forPanel($this->panelId)
            ->forTenant($this->tenant)
            ->count();

        $maxMemories = config('filament-copilot.memory.max_memories_per_user', 100);

        if ($currentCount >= $maxMemories) {
            return "Memory limit reached ({$maxMemories}). Please forget some memories first.";
        }

        CopilotAgentMemory::remember(
            participant: $this->user,
            panelId: $this->panelId,
            key: $key,
            value: $value,
            tenant: $this->tenant,
        );

        return "Remembered: {$key} = {$value}";
    }
}
