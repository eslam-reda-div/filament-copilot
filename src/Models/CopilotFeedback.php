<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Models;

use EslamRedaDiv\FilamentCopilot\Enums\FeedbackCategory;
use EslamRedaDiv\FilamentCopilot\Enums\FeedbackStatus;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A detailed report on a reply the user rated thumbs down: what went wrong,
 * what the answer should have been, and whether a new tool is needed — plus
 * the admin's review of it.
 */
class CopilotFeedback extends Model
{
    use HasUlids;

    protected $table = 'copilot_feedback';

    protected $fillable = [
        'message_id',
        'conversation_id',
        'participant_type',
        'participant_id',
        'panel_id',
        'tenant_type',
        'tenant_id',
        'category',
        'expected_answer',
        'suggests_new_tool',
        'status',
        'admin_notes',
        'reviewer_type',
        'reviewer_id',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'open',
        'suggests_new_tool' => false,
    ];

    protected function casts(): array
    {
        return [
            'category' => FeedbackCategory::class,
            'status' => FeedbackStatus::class,
            'suggests_new_tool' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CopilotMessage::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CopilotConversation::class, 'conversation_id');
    }

    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Reports nobody has reviewed yet.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FeedbackStatus::Open->value);
    }

    public function scopeForPanel(Builder $query, string $panelId): Builder
    {
        return $query->where('panel_id', $panelId);
    }

    /**
     * Record an admin's review. Moving a report back to "open" clears who
     * reviewed it and when.
     */
    public function review(FeedbackStatus $status, ?Model $reviewer = null, ?string $notes = null): static
    {
        $reviewed = $status->isReviewed();

        $this->forceFill([
            'status' => $status,
            'admin_notes' => $notes,
            'reviewer_type' => $reviewed ? $reviewer?->getMorphClass() : null,
            'reviewer_id' => $reviewed ? $reviewer?->getKey() : null,
            'reviewed_at' => $reviewed ? now() : null,
        ])->save();

        return $this;
    }

    /**
     * The user message the rated reply answered: the latest user message
     * in the conversation that is not newer than the reply.
     */
    public function promptMessage(): ?CopilotMessage
    {
        $reply = $this->message;

        if (! $reply) {
            return null;
        }

        return CopilotMessage::query()
            ->where('conversation_id', $reply->conversation_id)
            ->where('role', MessageRole::User->value)
            ->where('created_at', '<=', $reply->created_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function isReviewed(): bool
    {
        return $this->status?->isReviewed() ?? false;
    }
}
