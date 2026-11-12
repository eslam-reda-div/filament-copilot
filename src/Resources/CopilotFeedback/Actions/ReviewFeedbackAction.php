<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Actions;

use EslamRedaDiv\FilamentCopilot\Enums\FeedbackStatus;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin review step: set a status and leave notes. Shared by the
 * feedback table and the feedback view page.
 */
class ReviewFeedbackAction
{
    public static function make(): Action
    {
        return Action::make('review')
            ->label(__('filament-copilot::filament-copilot.feedback_review'))
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->modalHeading(__('filament-copilot::filament-copilot.feedback_review'))
            ->fillForm(fn (CopilotFeedback $record): array => [
                'status' => $record->status === FeedbackStatus::Open
                    ? FeedbackStatus::InReview->value
                    : $record->status?->value,
                'admin_notes' => $record->admin_notes,
            ])
            ->schema([
                Select::make('status')
                    ->label(__('filament-copilot::filament-copilot.status'))
                    ->options(FeedbackStatus::options())
                    ->required(),
                Textarea::make('admin_notes')
                    ->label(__('filament-copilot::filament-copilot.feedback_admin_notes'))
                    ->rows(4)
                    ->maxLength(5000),
            ])
            ->action(function (CopilotFeedback $record, array $data): void {
                $record->review(
                    FeedbackStatus::from($data['status']),
                    static::reviewer(),
                    filled($data['admin_notes'] ?? null) ? $data['admin_notes'] : null,
                );
            })
            ->successNotificationTitle(__('filament-copilot::filament-copilot.feedback_reviewed'));
    }

    /**
     * Bulk-set the status of the selected reports (keeping their notes).
     */
    public static function bulk(FeedbackStatus $status): BulkAction
    {
        return BulkAction::make('mark_'.$status->value)
            ->label(__('filament-copilot::filament-copilot.feedback_mark_as', ['status' => $status->getLabel()]))
            ->icon($status === FeedbackStatus::Resolved ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color($status->getColor())
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($status): void {
                $records->each(fn (CopilotFeedback $record) => $record->review($status, static::reviewer(), $record->admin_notes));
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function reviewer(): ?Model
    {
        $user = Filament::auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
