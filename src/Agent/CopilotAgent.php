<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent;

use EslamRedaDiv\FilamentCopilot\Agent\Middleware\AuditMiddleware;
use EslamRedaDiv\FilamentCopilot\Agent\Middleware\RateLimitMiddleware;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.3)]
#[MaxTokens(4096)]
class CopilotAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations {
        forUser as rememberForUser;
        continue as rememberContinue;
    }

    protected string $panelId;

    protected ?Model $tenant = null;

    protected Model $user;

    protected array $tools = [];

    protected ?string $systemPrompt = null;

    public function __construct(
        protected ContextBuilder $contextBuilder,
    ) {}

    public function forPanel(string $panelId): static
    {
        $this->panelId = $panelId;

        return $this;
    }

    public function forTenant(?Model $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function forUser(Model $user): static
    {
        $this->user = $user;
        $this->rememberForUser($user);

        return $this;
    }

    public function continue(string $conversationId, ?object $as = null): static
    {
        if (! $as instanceof Model) {
            throw new \InvalidArgumentException('A user model is required to continue a copilot conversation.');
        }

        $this->user = $as;
        $this->rememberContinue($conversationId, as: $as);

        return $this;
    }

    public function withTools(array $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    public function withSystemPrompt(?string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        return $this->contextBuilder
            ->forPanel($this->panelId)
            ->forTenant($this->tenant)
            ->forUser($this->user)
            ->withCustomPrompt($this->systemPrompt)
            ->build();
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    /**
     * The provider request timeout (laravel/ai calls this when no explicit
     * timeout is passed). Without it the SDK's 60 second default applied and
     * the `agent.timeout` setting had no effect, cutting off slow reasoning
     * models mid-answer.
     */
    public function timeout(): int
    {
        return max(1, (int) config('filament-copilot.agent.timeout', 120));
    }

    public function middleware(): array
    {
        return [
            new RateLimitMiddleware($this->panelId, $this->user, $this->tenant),
            new AuditMiddleware($this->panelId, $this->user, $this->tenant),
        ];
    }

    public function getPanelId(): string
    {
        return $this->panelId;
    }

    public function getUser(): Model
    {
        return $this->user;
    }

    public function getTenant(): ?Model
    {
        return $this->tenant;
    }
}
