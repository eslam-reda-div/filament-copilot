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
    protected bool $managementEnabled = false;

    protected ?string $managementGuard = null;

    protected ?string $provider = null;

    protected ?string $model = null;

    protected ?string $systemPrompt = null;

    /** @var array<\Laravel\Ai\Contracts\Tool> */
    protected array $globalTools = [];

    protected array $quickActions = [];

    protected ?Closure $authorizeUsing = null;

    protected ?bool $tokenBudgetEnabled = null;

    protected ?int $dailyTokenBudget = null;

    protected ?int $monthlyTokenBudget = null;

    protected ?bool $rateLimitEnabled = null;

    protected ?bool $memoryEnabled = null;

    protected ?int $maxMemoriesPerUser = null;

    protected ?bool $respectAuthorization = null;

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

    public function systemPrompt(?string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt ?? config('filament-copilot.system_prompt');
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

    public function tokenBudgetEnabled(bool $enabled = true): static
    {
        $this->tokenBudgetEnabled = $enabled;

        return $this;
    }

    public function isTokenBudgetEnabled(): bool
    {
        return $this->tokenBudgetEnabled ?? config('filament-copilot.token_budget.enabled', false);
    }

    public function dailyTokenBudget(?int $budget): static
    {
        $this->dailyTokenBudget = $budget;

        return $this;
    }

    public function getDailyTokenBudget(): ?int
    {
        return $this->dailyTokenBudget ?? config('filament-copilot.token_budget.daily_budget');
    }

    public function monthlyTokenBudget(?int $budget): static
    {
        $this->monthlyTokenBudget = $budget;

        return $this;
    }

    public function getMonthlyTokenBudget(): ?int
    {
        return $this->monthlyTokenBudget ?? config('filament-copilot.token_budget.monthly_budget');
    }

    public function rateLimitEnabled(bool $enabled = true): static
    {
        $this->rateLimitEnabled = $enabled;

        return $this;
    }

    public function isRateLimitEnabled(): bool
    {
        return $this->rateLimitEnabled ?? config('filament-copilot.rate_limits.enabled', false);
    }

    public function memoryEnabled(bool $enabled = true): static
    {
        $this->memoryEnabled = $enabled;

        return $this;
    }

    public function isMemoryEnabled(): bool
    {
        return $this->memoryEnabled ?? config('filament-copilot.memory.enabled', true);
    }

    public function maxMemoriesPerUser(int $max): static
    {
        $this->maxMemoriesPerUser = $max;

        return $this;
    }

    public function getMaxMemoriesPerUser(): int
    {
        return $this->maxMemoriesPerUser ?? config('filament-copilot.memory.max_memories_per_user', 100);
    }

    public function respectAuthorization(bool $respect = true): static
    {
        $this->respectAuthorization = $respect;

        return $this;
    }

    public function shouldRespectAuthorization(): bool
    {
        return $this->respectAuthorization ?? config('filament-copilot.respect_authorization', true);
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
        // Place the trigger button in the top bar next to global search
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_AFTER,
            fn (): string => auth()->check()
                ? Blade::render('@livewire(\'filament-copilot-button\')')
                : '',
        );

        // Place the chat modal at the end of the body
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => auth()->check()
                ? Blade::render('@livewire(\'filament-copilot-chat\')')
                : '',
        );
    }
}
