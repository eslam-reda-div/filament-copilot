<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Services;

use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Models\CopilotRateLimit;
use EslamRedaDiv\FilamentCopilot\Models\CopilotTokenUsage;
use Illuminate\Database\Eloquent\Model;

class RateLimitService
{
    /**
     * Check if a user can send a message.
     */
    public function canSendMessage(Model $user, string $panelId, ?Model $tenant = null): bool
    {
        $limits = $this->getLimits($user, $panelId, $tenant);

        if (! $limits) {
            return $this->checkDefaultLimits($user, $panelId, $tenant);
        }

        if (! $limits->copilot_enabled) {
            return false;
        }

        if ($limits->isCurrentlyBlocked()) {
            return false;
        }

        return $this->checkLimits($limits, $user, $panelId, $tenant);
    }

    /**
     * Record that a message was sent for rate limiting purposes.
     */
    public function recordMessage(Model $user, string $panelId, ?Model $tenant = null): void
    {
        // Token tracking is handled separately via CopilotTokenUsage
    }

    /**
     * Record token usage after a response.
     */
    public function recordTokenUsage(
        Model $user,
        string $panelId,
        int $inputTokens,
        int $outputTokens,
        ?Model $tenant = null,
        ?string $conversationId = null,
        ?string $model = null,
        ?string $provider = null,
    ): void {
        CopilotTokenUsage::record(
            participant: $user,
            panelId: $panelId,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            tenant: $tenant,
            conversation: $conversationId
                ? CopilotConversation::query()
                    ->forPanel($panelId)
                    ->forParticipant($user)
                    ->forTenant($tenant)
                    ->find($conversationId)
                : null,
            model: $model,
            provider: $provider,
        );
    }

    /**
     * Get remaining messages allowed for the current hour.
     */
    public function getRemainingMessages(Model $user, string $panelId, ?Model $tenant = null): ?int
    {
        $limits = $this->getLimits($user, $panelId, $tenant);
        $maxPerHour = $limits?->max_messages_per_hour ?? config('filament-copilot.rate_limits.max_messages_per_hour', 60);

        $used = $this->getMessagesInLastHour($user, $panelId, $tenant);

        return max(0, $maxPerHour - $used);
    }

    /**
     * Block a user from using copilot.
     */
    public function blockUser(Model $user, string $panelId, ?string $reason = null, ?\DateTimeInterface $until = null, ?Model $tenant = null): void
    {
        $limits = $this->getOrCreateLimits($user, $panelId, $tenant);
        $limits->block($reason, $until);
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(Model $user, string $panelId, ?Model $tenant = null): void
    {
        $limits = $this->getLimits($user, $panelId, $tenant);
        $limits?->unblock();
    }

    protected function getLimits(Model $user, string $panelId, ?Model $tenant): ?CopilotRateLimit
    {
        return CopilotRateLimit::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->first();
    }

    protected function getOrCreateLimits(Model $user, string $panelId, ?Model $tenant): CopilotRateLimit
    {
        return CopilotRateLimit::firstOrCreate(
            [
                'panel_id' => $panelId,
                'participant_type' => $user->getMorphClass(),
                'participant_id' => $user->getKey(),
                'tenant_type' => $tenant?->getMorphClass(),
                'tenant_id' => $tenant?->getKey(),
            ],
            [
                'max_messages_per_hour' => config('filament-copilot.rate_limits.max_messages_per_hour', 60),
                'max_messages_per_day' => config('filament-copilot.rate_limits.max_messages_per_day', 500),
                'max_tokens_per_hour' => config('filament-copilot.rate_limits.max_tokens_per_hour', 100000),
                'max_tokens_per_day' => config('filament-copilot.rate_limits.max_tokens_per_day', 1000000),
                'copilot_enabled' => true,
            ],
        );
    }

    protected function checkDefaultLimits(Model $user, string $panelId, ?Model $tenant): bool
    {
        $maxPerHour = config('filament-copilot.rate_limits.max_messages_per_hour', 60);
        $maxPerDay = config('filament-copilot.rate_limits.max_messages_per_day', 500);

        $messagesLastHour = $this->getMessagesInLastHour($user, $panelId, $tenant);
        $messagesLastDay = $this->getMessagesInLastDay($user, $panelId, $tenant);

        return $messagesLastHour < $maxPerHour && $messagesLastDay < $maxPerDay;
    }

    protected function checkLimits(CopilotRateLimit $limits, Model $user, string $panelId, ?Model $tenant): bool
    {
        $messagesLastHour = $this->getMessagesInLastHour($user, $panelId, $tenant);
        $messagesLastDay = $this->getMessagesInLastDay($user, $panelId, $tenant);

        if ($limits->max_messages_per_hour && $messagesLastHour >= $limits->max_messages_per_hour) {
            return false;
        }

        if ($limits->max_messages_per_day && $messagesLastDay >= $limits->max_messages_per_day) {
            return false;
        }

        // Check token limits
        $tokensLastHour = $this->getTokensInLastHour($user, $panelId, $tenant);
        $tokensLastDay = $this->getTokensInLastDay($user, $panelId, $tenant);

        if ($limits->max_tokens_per_hour && $tokensLastHour >= $limits->max_tokens_per_hour) {
            return false;
        }

        if ($limits->max_tokens_per_day && $tokensLastDay >= $limits->max_tokens_per_day) {
            return false;
        }

        return true;
    }

    protected function getMessagesInLastHour(Model $user, string $panelId, ?Model $tenant): int
    {
        return CopilotMessage::query()
            ->whereHas('conversation', function ($q) use ($user, $panelId, $tenant) {
                $q->forPanel($panelId)->forParticipant($user);
                if ($tenant) {
                    $q->forTenant($tenant);
                }
            })
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    protected function getMessagesInLastDay(Model $user, string $panelId, ?Model $tenant): int
    {
        return CopilotMessage::query()
            ->whereHas('conversation', function ($q) use ($user, $panelId, $tenant) {
                $q->forPanel($panelId)->forParticipant($user);
                if ($tenant) {
                    $q->forTenant($tenant);
                }
            })
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    protected function getTokensInLastHour(Model $user, string $panelId, ?Model $tenant): int
    {
        $query = CopilotTokenUsage::query()
            ->forParticipant($user)
            ->forPanel($panelId)
            ->where('created_at', '>=', now()->subHour());

        if ($tenant) {
            $query->where('tenant_type', $tenant->getMorphClass())
                ->where('tenant_id', $tenant->getKey());
        }

        return (int) $query->sum('total_tokens');
    }

    protected function getTokensInLastDay(Model $user, string $panelId, ?Model $tenant): int
    {
        $query = CopilotTokenUsage::query()
            ->forParticipant($user)
            ->forPanel($panelId)
            ->where('created_at', '>=', now()->subDay());

        if ($tenant) {
            $query->where('tenant_type', $tenant->getMorphClass())
                ->where('tenant_id', $tenant->getKey());
        }

        return (int) $query->sum('total_tokens');
    }
}
