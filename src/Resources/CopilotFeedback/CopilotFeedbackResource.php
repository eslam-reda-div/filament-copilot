<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Pages\ListCopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Pages\ViewCopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Schemas\CopilotFeedbackInfolist;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Tables\CopilotFeedbackTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Detailed thumbs-down reports, for admins to review and act on.
 */
class CopilotFeedbackResource extends Resource
{
    protected static ?string $model = CopilotFeedback::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hand-thumb-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.feedback');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.feedback_report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.feedback');
    }

    /**
     * The number of reports nobody has reviewed yet.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = CopilotFeedback::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        $guard = FilamentCopilotPlugin::get()->getManagementGuard();

        if ($guard) {
            try {
                return auth()->guard($guard)->check();
            } catch (\Throwable) {
                return false;
            }
        }

        return parent::canAccess();
    }

    public static function canCreate(): bool
    {
        // Reports come from the chat, never from the admin panel.
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CopilotFeedbackInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CopilotFeedbackTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotFeedback::route('/'),
            'view' => ViewCopilotFeedback::route('/{record}'),
        ];
    }
}
