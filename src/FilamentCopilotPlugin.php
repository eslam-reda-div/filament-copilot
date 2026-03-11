<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot;

use Closure;
use EslamRedaDiv\FilamentCopilot\Pages\CopilotDashboardPage;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotAuditLogs\CopilotAuditLogResource;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\CopilotConversationResource;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\CopilotRateLimitResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class FilamentCopilotPlugin implements Plugin
{
    protected bool $chatEnabled = true;

    protected bool $managementEnabled = false;

    protected ?string $managementGuard = null;

    protected ?string $provider = null;

    protected ?string $model = null;

    protected bool $shouldThink = false;

    protected bool $shouldPlan = false;

    protected bool $shouldApprovePlan = false;

    protected ?int $maxSteps = null;

    protected ?float $temperature = null;

    protected ?string $systemPrompt = null;

    protected ?int $maxConversationMessages = null;

    /** @var array<\Laravel\Ai\Contracts\Tool> */
    protected array $globalTools = [];

    protected array $quickActions = [];

    protected ?Closure $authorizeUsing = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-copilot';
    }

    public function chatEnabled(bool $enabled = true): static
    {
        $this->chatEnabled = $enabled;

        return $this;
    }

    public function isChatEnabled(): bool
    {
        return $this->chatEnabled && config('filament-copilot.chat.enabled', true);
    }

    public function managementEnabled(bool $enabled = true): static
    {
        $this->managementEnabled = $enabled;

        return $this;
    }

    public function isManagementEnabled(): bool
    {
        return $this->managementEnabled || config('filament-copilot.management.enabled', false);
    }

    public function managementGuard(?string $guard): static
    {
        $this->managementGuard = $guard;

        return $this;
    }

    public function getManagementGuard(): ?string
    {
        return $this->managementGuard ?? config('filament-copilot.management.guard');
    }

    public function provider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider ?? config('filament-copilot.provider', 'openai');
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model ?? config('filament-copilot.model');
    }

    public function thinking(bool $shouldThink = true): static
    {
        $this->shouldThink = $shouldThink;

        return $this;
    }

    public function shouldThink(): bool
    {
        return $this->shouldThink || config('filament-copilot.agent.should_think', false);
    }

    public function maxSteps(int $maxSteps): static
    {
        $this->maxSteps = $maxSteps;

        return $this;
    }

    public function getMaxSteps(): int
    {
        return $this->maxSteps ?? config('filament-copilot.agent.max_steps', 10);
    }

    public function temperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature ?? config('filament-copilot.agent.temperature', 0.3);
    }

    public function systemPrompt(?string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt ?? config('filament-copilot.system_prompt');
    }

    public function maxConversationMessages(int $max): static
    {
        $this->maxConversationMessages = $max;

        return $this;
    }

    public function getMaxConversationMessages(): int
    {
        return $this->maxConversationMessages ?? config('filament-copilot.chat.max_conversation_messages', 50);
    }

    public function planning(bool $shouldPlan = true): static
    {
        $this->shouldPlan = $shouldPlan;

        return $this;
    }

    public function shouldPlan(): bool
    {
        return $this->shouldPlan || config('filament-copilot.agent.should_plan', false);
    }

    public function shouldApprovePlan(bool $shouldApprove = true): static
    {
        $this->shouldApprovePlan = $shouldApprove;

        return $this;
    }

    public function requiresPlanApproval(): bool
    {
        return $this->shouldApprovePlan || config('filament-copilot.agent.should_approve_plan', false);
    }

    public function globalTools(array $tools): static
    {
        $this->globalTools = $tools;

        return $this;
    }

    public function getGlobalTools(): array
    {
        return ! empty($this->globalTools) ? $this->globalTools : config('filament-copilot.global_tools', []);
    }

    public function quickActions(array $actions): static
    {
        $this->quickActions = $actions;

        return $this;
    }

    public function getQuickActions(): array
    {
        return ! empty($this->quickActions) ? $this->quickActions : config('filament-copilot.quick_actions', []);
    }

    public function authorizeUsing(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function getAuthorizeUsing(): ?Closure
    {
        return $this->authorizeUsing;
    }

    public function register(Panel $panel): void
    {
        if ($this->managementEnabled) {
            $panel->resources([
                CopilotConversationResource::class,
                CopilotAuditLogResource::class,
                CopilotRateLimitResource::class,
            ]);

            $panel->pages([
                CopilotDashboardPage::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        if ($this->isChatEnabled()) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check()
                    ? Blade::render('@livewire(\'filament-copilot-chat\') @livewire(\'filament-copilot-button\')')
                    : '',
            );
        }
    }
}
