<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotRateLimit;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Pages\CreateCopilotRateLimit;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Pages\EditCopilotRateLimit;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Pages\ListCopilotRateLimits;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Schemas\CopilotRateLimitForm;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotRateLimits\Tables\CopilotRateLimitsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CopilotRateLimitResource extends Resource
{
    protected static ?string $model = CopilotRateLimit::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | \UnitEnum | null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.rate_limits');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.rate_limit');
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

    public static function form(Schema $schema): Schema
    {
        return CopilotRateLimitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CopilotRateLimitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotRateLimits::route('/'),
            'create' => CreateCopilotRateLimit::route('/create'),
            'edit' => EditCopilotRateLimit::route('/{record}/edit'),
        ];
    }
}
