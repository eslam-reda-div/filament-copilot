<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent\Middleware;

use Closure;
use EslamRedaDiv\FilamentCopilot\Events\CopilotRateLimitExceeded;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Agent middleware enforcing the per-user message limits.
 *
 * laravel/ai 0.x runs agent middleware once per run with the AgentPrompt;
 * 1.x runs it around every generation step with a PendingStep. Either way
 * one user message is checked and counted exactly once: on 1.x only the
 * first step of a run does so, the tool-calling steps after it pass through.
 */
class RateLimitMiddleware
{
    public function __construct(
        protected string $panelId,
        protected Model $user,
        protected ?Model $tenant = null,
    ) {}

    /**
     * @param  AgentPrompt|PendingStep  $prompt
     */
    public function handle(object $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.rate_limits.enabled', false) || ! static::startsRun($prompt)) {
            return $next($prompt);
        }

        /** @var RateLimitService $service */
        $service = app(RateLimitService::class);

        if (! $service->canSendMessage($this->user, $this->panelId, $this->tenant)) {
            event(new CopilotRateLimitExceeded($this->user, $this->panelId, $this->tenant));

            throw new \RuntimeException(__('filament-copilot::filament-copilot.rate_limit_exceeded'));
        }

        $response = $next($prompt);

        $service->recordMessage($this->user, $this->panelId, $this->tenant);

        return $response;
    }

    /**
     * Whether this invocation is the start of an agent run: always on 0.x
     * (one AgentPrompt per run), only the first step on 1.x.
     */
    public static function startsRun(object $prompt): bool
    {
        return method_exists($prompt, 'isFirstStep') ? $prompt->isFirstStep() : true;
    }
}
