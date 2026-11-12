<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Tables;

use EslamRedaDiv\FilamentCopilot\Models\CopilotRateLimit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CopilotRateLimitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->badge(),
                TextColumn::make('participant_type')
                    ->label(__('filament-copilot::filament-copilot.participant_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.participant_id')),
                TextColumn::make('max_messages_per_hour')
                    ->label(__('filament-copilot::filament-copilot.msg_hour'))
                    ->numeric(),
                TextColumn::make('max_messages_per_day')
                    ->label(__('filament-copilot::filament-copilot.msg_day'))
                    ->numeric(),
                IconColumn::make('copilot_enabled')
                    ->label(__('filament-copilot::filament-copilot.enabled'))
                    ->boolean(),
                IconColumn::make('is_blocked')
                    ->label(__('filament-copilot::filament-copilot.blocked'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('blocked_until')
                    ->label(__('filament-copilot::filament-copilot.blocked_until'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_blocked')
                    ->label(__('filament-copilot::filament-copilot.blocked')),
                TernaryFilter::make('copilot_enabled')
                    ->label(__('filament-copilot::filament-copilot.enabled')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('block')
                    ->label(__('filament-copilot::filament-copilot.block'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CopilotRateLimit $record) => ! $record->is_blocked)
                    ->action(fn (CopilotRateLimit $record) => $record->block(__('filament-copilot::filament-copilot.blocked_by_admin'))),
                Action::make('unblock')
                    ->label(__('filament-copilot::filament-copilot.unblock'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CopilotRateLimit $record) => $record->is_blocked)
                    ->action(fn (CopilotRateLimit $record) => $record->unblock()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
