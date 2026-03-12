<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Tools;

use EslamRedaDiv\FilamentCopilot\Events\CopilotToolExecuted;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Tools\Concerns\LogsAudit;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Tool;

abstract class BaseTool implements Tool
{
    use LogsAudit;

    protected string $panelId;

    protected Model $user;

    protected ?Model $tenant = null;

    protected ?string $conversationId = null;

    public function forPanel(string $panelId): static
    {
        $this->panelId = $panelId;

        return $this;
    }

    public function forUser(Model $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function forTenant(?Model $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function forConversation(?string $conversationId): static
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    protected function dispatchToolExecuted(string $toolName, string $result, ?string $messageId = null, ?array $input = null): void
    {
        $toolCall = new CopilotToolCall([
            'message_id' => $messageId,
            'tool_name' => $toolName,
            'input' => $input ?? [],
            'output' => $result,
            'status' => 'completed',
        ]);

        event(new CopilotToolExecuted(
            toolCall: $toolCall,
            toolName: $toolName,
            result: $result,
        ));
    }
}
