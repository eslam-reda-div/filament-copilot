<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent\Middleware;

use Closure;
use EslamRedaDiv\FilamentCopilot\Events\CopilotRateLimitExceeded;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Prompts\AgentPrompt;

class RateLimitMiddleware
{
    public function __construct(
        protected string $panelId,
        protected Model $user,
        protected ?Model $tenant = null,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        if (! config('filament-copilot.rate_limits.enabled', false)) {
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
}
