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

        $label = fn (string $key, array $replace = []): string => __("filament-copilot::filament-copilot.{$key}", $replace);

        $lines = [
            "# {$conversation->title}",
            '',
            '**'.$label('export_date').":** {$conversation->created_at->format('Y-m-d H:i')}",
            '**'.$label('export_panel').":** {$conversation->panel_id}",
            '',
            '---',
            '',
        ];

        foreach ($conversation->messages as $message) {
            // Turns that only called tools or paused for approval have no text.
            if (blank($message->content)) {
                continue;
            }

            $role = match ($message->role->value) {
                'user' => $label('export_you'),
                'assistant' => $label('export_copilot'),
                'system' => $label('export_system'),
                'tool' => $label('export_tool'),
            };

            $lines[] = "**{$role}:**";
            $lines[] = '';
            $lines[] = $message->content;
            $lines[] = '';
        }

        $totalTokens = $conversation->messages->sum(fn (CopilotMessage $m) => ($m->input_tokens ?? 0) + ($m->output_tokens ?? 0));
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '*'.$label('export_total_tokens', ['count' => $totalTokens]).'*';

        return implode("\n", $lines);
    }
}
