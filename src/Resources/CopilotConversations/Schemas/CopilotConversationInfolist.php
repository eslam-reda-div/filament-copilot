<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CopilotConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-copilot::filament-copilot.conversation_details'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('filament-copilot::filament-copilot.title')),
                        TextEntry::make('panel_id')
                            ->label(__('filament-copilot::filament-copilot.panel'))
                            ->badge(),
                        TextEntry::make('participant_type')
                            ->label(__('filament-copilot::filament-copilot.participant_type')),
                        TextEntry::make('participant_id')
                            ->label(__('filament-copilot::filament-copilot.participant_id')),
                        TextEntry::make('created_at')
                            ->label(__('filament-copilot::filament-copilot.created_at'))
                            ->dateTime(),
                    ])->columns(3),
                Section::make(__('filament-copilot::filament-copilot.messages'))
                    ->schema([
                        RepeatableEntry::make('messages')
                            ->schema([
                                TextEntry::make('role')
                                    ->label(__('filament-copilot::filament-copilot.role'))
                                    ->badge()
                                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                                        'user' => 'info',
                                        'assistant' => 'success',
                                        'system' => 'warning',
                                        'tool' => 'gray',
                                        default => 'gray',
                                    }),
                                TextEntry::make('rating')
                                    ->label(__('filament-copilot::filament-copilot.feedback_rating'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => ($state?->value ?? $state) === 'positive'
                                        ? __('filament-copilot::filament-copilot.thumbs_up')
                                        : __('filament-copilot::filament-copilot.thumbs_down'))
                                    ->color(fn ($state): string => ($state?->value ?? $state) === 'positive' ? 'success' : 'danger')
                                    ->visible(fn ($record): bool => $record->rating !== null),
                                TextEntry::make('feedback.category')
                                    ->label(__('filament-copilot::filament-copilot.feedback_category'))
                                    ->badge()
                                    ->visible(fn ($record): bool => $record->feedback !== null),
                                TextEntry::make('content')
                                    ->label(__('filament-copilot::filament-copilot.content'))
                                    ->markdown()
                                    ->columnSpanFull(),
                                TextEntry::make('input_tokens')
                                    ->label(__('filament-copilot::filament-copilot.input_tokens'))
                                    ->numeric()
                                    ->placeholder('—')
                                    ->visible(fn ($record): bool => $record->input_tokens !== null),
                                TextEntry::make('output_tokens')
                                    ->label(__('filament-copilot::filament-copilot.output_tokens'))
                                    ->numeric()
                                    ->placeholder('—')
                                    ->visible(fn ($record): bool => $record->output_tokens !== null),
                                TextEntry::make('created_at')
                                    ->label(__('filament-copilot::filament-copilot.time'))
                                    ->dateTime(),
                            ])->columns(4),
                    ]),
            ]);
    }
}
