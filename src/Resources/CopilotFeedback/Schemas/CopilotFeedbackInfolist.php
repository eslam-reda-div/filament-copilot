<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Schemas;

use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\CopilotConversationResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CopilotFeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-copilot::filament-copilot.feedback'))
                    ->schema([
                        TextEntry::make('category')
                            ->label(__('filament-copilot::filament-copilot.feedback_category'))
                            ->badge(),
                        TextEntry::make('status')
                            ->label(__('filament-copilot::filament-copilot.status'))
                            ->badge(),
                        IconEntry::make('suggests_new_tool')
                            ->label(__('filament-copilot::filament-copilot.feedback_new_tool'))
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label(__('filament-copilot::filament-copilot.created_at'))
                            ->dateTime(),
                        TextEntry::make('expected_answer')
                            ->label(__('filament-copilot::filament-copilot.feedback_expected_answer'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])->columns(4),

                // The exchange in context: what the user asked and what the
                // copilot answered.
                Section::make(__('filament-copilot::filament-copilot.feedback_exchange'))
                    ->schema([
                        TextEntry::make('prompt')
                            ->label(__('filament-copilot::filament-copilot.feedback_prompt'))
                            ->state(fn (CopilotFeedback $record): ?string => $record->promptMessage()?->content)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('message.content')
                            ->label(__('filament-copilot::filament-copilot.feedback_reply'))
                            ->markdown()
                            ->columnSpanFull(),
                        TextEntry::make('conversation.title')
                            ->label(__('filament-copilot::filament-copilot.conversation'))
                            ->url(fn (CopilotFeedback $record): ?string => static::conversationUrl($record)),
                    ]),

                Section::make(__('filament-copilot::filament-copilot.feedback_review'))
                    ->schema([
                        TextEntry::make('admin_notes')
                            ->label(__('filament-copilot::filament-copilot.feedback_admin_notes'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('reviewer_id')
                            ->label(__('filament-copilot::filament-copilot.feedback_reviewer'))
                            ->placeholder('—'),
                        TextEntry::make('reviewed_at')
                            ->label(__('filament-copilot::filament-copilot.feedback_reviewed_at'))
                            ->dateTime()
                            ->placeholder('—'),
                    ])->columns(2),
            ]);
    }

    protected static function conversationUrl(CopilotFeedback $record): ?string
    {
        try {
            return CopilotConversationResource::getUrl('view', ['record' => $record->conversation_id]);
        } catch (\Throwable) {
            // The conversations resource isn't registered on this panel.
            return null;
        }
    }
}
