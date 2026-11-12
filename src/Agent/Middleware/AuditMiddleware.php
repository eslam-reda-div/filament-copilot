<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent\Middleware;

use Closure;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAuditLog;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\PendingStep;
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

    /**
     * laravel/ai 0.x calls this once per run with the AgentPrompt; 1.x calls
     * it around every generation step with a PendingStep. Either way one
     * MessageSent / ResponseReceived pair is logged per run.
     *
     * @param  AgentPrompt|PendingStep  $prompt
     */
    public function handle(object $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.audit.enabled', true) || ! config('filament-copilot.audit.log_messages', true)) {
            return $next($prompt);
        }

        if ($prompt instanceof PendingStep) {
            return $this->handleStep($prompt, $next);
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

    /**
     * laravel/ai 1.x: log the user's message on the run's first step, and the
     * response once the step that ends the run resolves — one that called no
     * further tools, or paused for a human approval.
     */
    protected function handleStep(PendingStep $step, Closure $next): mixed
    {
        if ($step->isFirstStep()) {
            AuditMiddleware::logAction(
                AuditAction::MessageSent,
                $this->user,
                $this->panelId,
                $this->tenant,
                $this->conversationId,
            );
        }

        $result = $next($step);

        if (is_object($result) && method_exists($result, 'then')) {
            $result->then(function (object $response): void {
                $endsRun = ($response->toolCalls ?? []) === [] || ($response->pendingApprovals ?? []) !== [];

                if ($endsRun) {
                    AuditMiddleware::logAction(
                        AuditAction::ResponseReceived,
                        $this->user,
                        $this->panelId,
                        $this->tenant,
                        $this->conversationId,
                    );
                }
            });
        }

        return $result;
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
