<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotConversations\Tables;

use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CopilotConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-copilot::filament-copilot.title'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->badge(),
                TextColumn::make('participant_type')
                    ->label(__('filament-copilot::filament-copilot.participant_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.participant_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('messages_count')
                    ->label(__('filament-copilot::filament-copilot.messages'))
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('negative_rating_count')
                    ->label(__('filament-copilot::filament-copilot.thumbs_down'))
                    ->counts([
                        'messages as negative_rating_count' => fn (Builder $query): Builder => $query
                            ->where('rating', MessageRating::Negative->value),
                    ])
                    ->badge()
                    ->color('danger')
                    // A zero badge on nearly every row is noise — only flag the
                    // conversations somebody actually complained about.
                    ->formatStateUsing(fn ($state): ?string => ((int) $state) > 0 ? (string) $state : null)
                    ->placeholder('')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-copilot::filament-copilot.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament-copilot::filament-copilot.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->options(fn () => CopilotConversation::query()
                        ->distinct()
                        ->pluck('panel_id', 'panel_id')
                        ->toArray()),
                TernaryFilter::make('has_negative_rating')
                    ->label(__('filament-copilot::filament-copilot.has_negative_rating'))
                    ->placeholder(__('filament-copilot::filament-copilot.all_conversations'))
                    ->trueLabel(__('filament-copilot::filament-copilot.with_negative_rating'))
                    ->falseLabel(__('filament-copilot::filament-copilot.without_negative_rating'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereHas('messages', fn (Builder $messages): Builder => $messages
                                ->where('rating', MessageRating::Negative->value)),
                        false: fn (Builder $query): Builder => $query
                            ->whereDoesntHave('messages', fn (Builder $messages): Builder => $messages
                                ->where('rating', MessageRating::Negative->value)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
