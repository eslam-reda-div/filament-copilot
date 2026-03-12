<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Models;

use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopilotMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
        'input_tokens',
        'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CopilotConversation::class, 'conversation_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(CopilotToolCall::class, 'message_id');
    }

    public function scopeByRole($query, MessageRole $role)
    {
        return $query->where('role', $role);
    }

    public function isFromUser(): bool
    {
        return $this->role === MessageRole::User;
    }

    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::Assistant;
    }

    public function isToolMessage(): bool
    {
        return $this->role === MessageRole::Tool;
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }
}
