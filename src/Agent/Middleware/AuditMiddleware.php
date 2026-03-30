<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent\Middleware;

use Closure;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAuditLog;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Prompts\AgentPrompt;

class AuditMiddleware
{
    public function __construct(
        protected string $panelId,
        protected Model $user,
        protected ?Model $tenant = null,
        protected ?string $conversationId = null,
    ) {}

    public function withConversationId(string $id): static
    {
        $this->conversationId = $id;

        return $this;
    }

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.audit.enabled', true)) {
            return $next($prompt);
        }

        if (config('filament-copilot.audit.log_messages', true)) {
            AuditMiddleware::logAction(
                AuditAction::MessageSent,
                $this->user,
                $this->panelId,
                $this->tenant,
                $this->conversationId,
            );
        }

        $response = $next($prompt);

        if (config('filament-copilot.audit.log_messages', true)) {
            AuditMiddleware::logAction(
                AuditAction::ResponseReceived,
                $this->user,
                $this->panelId,
                $this->tenant,
                $this->conversationId,
            );
        }

        return $response;
    }

    public static function logAction(
        AuditAction $action,
        Model $user,
        string $panelId,
        ?Model $tenant = null,
        ?string $conversationId = null,
        ?string $resourceType = null,
        ?string $recordKey = null,
        ?array $payload = null,
    ): void {
        $conversation = $conversationId
            ? CopilotConversation::query()
                ->forPanel($panelId)
                ->forParticipant($user)
                ->forTenant($tenant)
                ->find($conversationId)
            : null;

        CopilotAuditLog::log(
            action: $action,
            participant: $user,
            panelId: $panelId,
            tenant: $tenant,
            conversation: $conversation,
            resourceType: $resourceType,
            recordKey: $recordKey,
            payload: $payload,
        );
    }
}
