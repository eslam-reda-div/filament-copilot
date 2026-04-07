<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotAuditLogs;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAuditLog;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotAuditLogs\Pages\ListCopilotAuditLogs;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotAuditLogs\Tables\CopilotAuditLogsTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CopilotAuditLogResource extends Resource
{
    protected static ?string $model = CopilotAuditLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static string | \UnitEnum | null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.audit_logs');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.audit_log');
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
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return CopilotAuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotAuditLogs::route('/'),
        ];
    }
}
