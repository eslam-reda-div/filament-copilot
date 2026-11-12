<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Tables;

use EslamRedaDiv\FilamentCopilot\Enums\FeedbackCategory;
use EslamRedaDiv\FilamentCopilot\Enums\FeedbackStatus;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Actions\ReviewFeedbackAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CopilotFeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['message', 'conversation']))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament-copilot::filament-copilot.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('filament-copilot::filament-copilot.feedback_category'))
                    ->badge(),
                TextColumn::make('message.content')
                    ->label(__('filament-copilot::filament-copilot.feedback_reply'))
                    ->limit(60)
                    ->tooltip(fn (CopilotFeedback $record): ?string => $record->message?->content)
                    ->wrap(),
                TextColumn::make('expected_answer')
                    ->label(__('filament-copilot::filament-copilot.feedback_expected_answer'))
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('suggests_new_tool')
                    ->label(__('filament-copilot::filament-copilot.feedback_new_tool'))
                    ->boolean()
                    ->trueIcon('heroicon-o-wrench-screwdriver')
                    ->falseIcon('heroicon-o-minus')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('filament-copilot::filament-copilot.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('conversation.title')
                    ->label(__('filament-copilot::filament-copilot.conversation'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.participant_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')
                    ->label(__('filament-copilot::filament-copilot.feedback_reviewed_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-copilot::filament-copilot.status'))
                    ->options(FeedbackStatus::options()),
                SelectFilter::make('category')
                    ->label(__('filament-copilot::filament-copilot.feedback_category'))
                    ->options(FeedbackCategory::options()),
                TernaryFilter::make('suggests_new_tool')
                    ->label(__('filament-copilot::filament-copilot.feedback_new_tool')),
                SelectFilter::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->options(fn (): array => CopilotFeedback::query()
                        ->distinct()
                        ->pluck('panel_id', 'panel_id')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ReviewFeedbackAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ReviewFeedbackAction::bulk(FeedbackStatus::Resolved),
                    ReviewFeedbackAction::bulk(FeedbackStatus::Dismissed),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
