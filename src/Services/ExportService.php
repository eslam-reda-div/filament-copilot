<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Services;

use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use Illuminate\Database\Eloquent\Model;

class ExportService
{
    /**
     * Export a conversation to Markdown.
     */
    public function toMarkdown(string $conversationId, Model $user, string $panelId, ?Model $tenant = null): ?string
    {
        $conversation = CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->with('messages')
            ->find($conversationId);

        if (! $conversation) {
            return null;
        }

        $lines = [
            "# {$conversation->title}",
            '',
            "**Date:** {$conversation->created_at->format('Y-m-d H:i')}",
            "**Panel:** {$conversation->panel_id}",
            '',
            '---',
            '',
        ];

        foreach ($conversation->messages as $message) {
            $role = match ($message->role->value) {
                'user' => '**You:**',
                'assistant' => '**Copilot:**',
                'system' => '**System:**',
                'tool' => '**Tool:**',
            };

            $lines[] = $role;
            $lines[] = '';
            $lines[] = $message->content;
            $lines[] = '';
        }

        $totalTokens = $conversation->messages->sum(fn (CopilotMessage $m) => ($m->input_tokens ?? 0) + ($m->output_tokens ?? 0));
        $lines[] = '---';
        $lines[] = '';
        $lines[] = "*Total tokens used: {$totalTokens}*";

        return implode("\n", $lines);
    }
}
