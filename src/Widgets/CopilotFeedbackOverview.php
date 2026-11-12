<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Widgets;

use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How the copilot's replies were rated over the last 30 days, and how many
 * detailed reports are still waiting for review.
 */
class CopilotFeedbackOverview extends StatsOverviewWidget
{
    public const PERIOD_DAYS = 30;

    protected static ?int $sort = 1;

    /**
     * @return array{positive: int, negative: int, satisfaction: ?int, open: int}
     */
    public static function summary(): array
    {
        $ratings = CopilotMessage::query()
            ->whereNotNull('rating')
            ->where('created_at', '>=', now()->subDays(self::PERIOD_DAYS))
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $positive = (int) ($ratings[MessageRating::Positive->value] ?? 0);
        $negative = (int) ($ratings[MessageRating::Negative->value] ?? 0);
        $rated = $positive + $negative;

        return [
            'positive' => $positive,
            'negative' => $negative,
            'satisfaction' => $rated > 0 ? (int) round($positive / $rated * 100) : null,
            'open' => CopilotFeedback::query()->open()->count(),
        ];
    }

    protected function getStats(): array
    {
        $summary = static::summary();

        return [
            Stat::make(
                __('filament-copilot::filament-copilot.feedback_satisfaction'),
                $summary['satisfaction'] === null ? '—' : $summary['satisfaction'].'%',
            )
                ->description(__('filament-copilot::filament-copilot.feedback_last_days', ['days' => self::PERIOD_DAYS]))
                ->icon('heroicon-o-face-smile'),
            Stat::make(__('filament-copilot::filament-copilot.thumbs_up'), number_format($summary['positive']))
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success'),
            Stat::make(__('filament-copilot::filament-copilot.thumbs_down'), number_format($summary['negative']))
                ->icon('heroicon-o-hand-thumb-down')
                ->color('danger'),
            Stat::make(__('filament-copilot::filament-copilot.feedback_open_reports'), number_format($summary['open']))
                ->icon('heroicon-o-inbox')
                ->color($summary['open'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
