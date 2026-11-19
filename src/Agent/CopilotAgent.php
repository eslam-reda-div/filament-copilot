<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent;

use EslamRedaDiv\FilamentCopilot\Agent\Middleware\AuditMiddleware;
use EslamRedaDiv\FilamentCopilot\Agent\Middleware\RateLimitMiddleware;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Promptable;
use Stringable;

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
     * Maximum output tokens per model call, from `filament-copilot.agent.max_tokens`.
     *
     * Methods rather than #[MaxTokens] / #[Temperature] attributes: laravel/ai
     * reads attributes off the concrete class, and PHP attributes are not
     * inherited, so an app agent extending this one used to silently lose both.
     * laravel/ai also prefers a non-null method result over any attribute, so
     * an extending agent that declares the attribute itself is honoured here.
     */
    public function maxTokens(): ?int
    {
        if (($declared = $this->declaredAttributeValue(MaxTokens::class)) !== null) {
            return (int) $declared;
        }

        $maxTokens = config('filament-copilot.agent.max_tokens', 4096);

        return blank($maxTokens) ? null : (int) $maxTokens;
    }

    /**
     * Sampling temperature, from `filament-copilot.agent.temperature`.
     *
     * A #[Temperature] attribute on an extending agent takes precedence.
     */
    public function temperature(): ?float
    {
        if (($declared = $this->declaredAttributeValue(Temperature::class)) !== null) {
            return (float) $declared;
        }

        $temperature = config('filament-copilot.agent.temperature', 0.3);

        return blank($temperature) ? null : (float) $temperature;
    }

    /**
     * The provider request timeout in seconds (laravel/ai calls this when no
     * explicit timeout is passed), from `filament-copilot.agent.timeout`.
     *
     * A #[Timeout] attribute on an extending agent takes precedence: laravel/ai
     * only reads the attribute when no timeout() method exists. A null or
     * empty config value falls back to 120 seconds rather than 1.
     */
    public function timeout(): int
    {
        if (($declared = $this->declaredAttributeValue(Timeout::class)) !== null) {
            return (int) $declared;
        }

        $timeout = config('filament-copilot.agent.timeout');

        return max(1, blank($timeout) ? 120 : (int) $timeout);
    }

    /**
     * The value of a laravel/ai option attribute declared on the concrete agent class.
     *
     * @param  class-string  $attribute
     */
    protected function declaredAttributeValue(string $attribute): int|float|null
    {
        $attributes = (new \ReflectionClass($this))->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance()->value;
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
