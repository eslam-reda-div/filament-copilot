<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Widgets;

use EslamRedaDiv\FilamentCopilot\Models\CopilotTokenUsage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TokenUsageChart extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('filament-copilot::filament-copilot.token_usage_last_30_days');
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $usage = CopilotTokenUsage::query()
            ->where('usage_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('usage_date, SUM(input_tokens) as input_sum, SUM(output_tokens) as output_sum')
            ->groupBy('usage_date')
            ->pluck('input_sum', 'usage_date')
            ->toArray();

        $outputUsage = CopilotTokenUsage::query()
            ->where('usage_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('usage_date, SUM(output_tokens) as output_sum')
            ->groupBy('usage_date')
            ->pluck('output_sum', 'usage_date')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => __('filament-copilot::filament-copilot.input_tokens'),
                    'data' => $days->map(fn ($d) => (int) ($usage[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('filament-copilot::filament-copilot.output_tokens'),
                    'data' => $days->map(fn ($d) => (int) ($outputUsage[$d] ?? 0))->values()->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
