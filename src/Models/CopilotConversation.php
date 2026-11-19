<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Models\Conversation as AiConversation;
use Laravel\Ai\Models\ConversationMessage as AiConversationMessage;
use Throwable;

class CopilotConversation extends Model
{
    use HasUlids;

    protected $fillable = [
        'participant_type',
        'participant_id',
        'panel_id',
        'tenant_type',
        'tenant_id',
        'title',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Delete the laravel/ai conversation along with this one, on every model
     * delete — the chat sidebar, the management resource's delete and
     * bulk-delete actions, or app code calling delete() on the model.
     *
     * It runs on `deleting`, so a failure cancels the copilot delete instead
     * of leaving the transcript behind with no conversation left to delete it
     * from. The exception is reported and delete() returns false, which
     * Filament's delete actions show as their failure notification.
     *
     * Query-builder deletes — CopilotConversation::query()->delete(), or a
     * DeleteBulkAction using fetchSelectedRecords(false) — fire no model
     * events and bypass this; call deleteAiConversation() for those yourself.
     */
    protected static function booted(): void
    {
        static::deleting(function (CopilotConversation $conversation): ?bool {
            try {
                $conversation->deleteAiConversation();
            } catch (Throwable $exception) {
                report($exception);

                return false;
            }

            return null;
        });
    }

    /**
     * Delete the laravel/ai conversation that holds this conversation's
     * history (agent_conversations + agent_conversation_messages).
     *
     * Without this, deleting a copilot conversation left the full transcript
     * — every prompt, reply and tool result — behind in laravel/ai's tables.
     * Only the conversation recorded in metadata is touched, and only when it
     * belongs to the same participant. The messages and the conversation are
     * deleted in one transaction on laravel/ai's connection, so a failure
     * leaves the transcript intact. An app without laravel/ai's conversation
     * tables (a custom ConversationStore) is skipped; any other failure is
     * thrown.
     */
    public function deleteAiConversation(): void
    {
        $aiConversationId = $this->metadata['ai_conversation_id'] ?? null;

        if (! is_string($aiConversationId) || $aiConversationId === '') {
            return;
        }

        if (! static::hasAiConversationTables()) {
            return;
        }

        $aiConversation = AiConversation::query()
            ->whereKey($aiConversationId)
            ->where('participant_type', $this->participant_type)
            ->where('participant_id', $this->participant_id)
            ->first();

        if (! $aiConversation) {
            return;
        }

        $aiConversation->getConnection()->transaction(function () use ($aiConversation): void {
            $aiConversation->messages()->delete();
            $aiConversation->delete();
        });
    }

    /**
     * Whether laravel/ai's conversation tables exist, on its configured connection.
     */
    protected static function hasAiConversationTables(): bool
    {
        foreach ([new AiConversation, new AiConversationMessage] as $model) {
            if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
                return false;
            }
        }

        return true;
    }

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CopilotMessage::class, 'conversation_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(CopilotFeedback::class, 'conversation_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CopilotAuditLog::class, 'conversation_id');
    }

    public function tokenUsages(): HasMany
    {
        return $this->hasMany(CopilotTokenUsage::class, 'conversation_id');
    }

    public function latestMessage(): HasMany
    {
        return $this->messages()->latest()->limit(1);
    }

    public function scopeForPanel($query, string $panelId)
    {
        return $query->where('panel_id', $panelId);
    }

    public function scopeForParticipant($query, Model $participant)
    {
        return $query
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey());
    }

    public function scopeForTenant($query, ?Model $tenant)
    {
        if ($tenant === null) {
            return $query->whereNull('tenant_type')->whereNull('tenant_id');
        }

        return $query
            ->where('tenant_type', $tenant->getMorphClass())
            ->where('tenant_id', $tenant->getKey());
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->messages()->sum('input_tokens') + $this->messages()->sum('output_tokens');
    }
}
