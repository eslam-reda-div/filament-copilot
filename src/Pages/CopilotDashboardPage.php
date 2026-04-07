<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Pages;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Widgets\CopilotStatsOverview;
use EslamRedaDiv\FilamentCopilot\Widgets\TokenUsageChart;
use EslamRedaDiv\FilamentCopilot\Widgets\TopUsersTable;
use Filament\Pages\Page;

class CopilotDashboardPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament-copilot::pages.copilot-dashboard';

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

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.dashboard');
    }

    public function getTitle(): string
    {
        return __('filament-copilot::filament-copilot.copilot_dashboard');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CopilotStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TokenUsageChart::class,
            TopUsersTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}
