<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a feedback report is in the admin review workflow.
 */
enum FeedbackStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return __("filament-copilot::filament-copilot.feedback_status_{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InReview => 'info',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    /**
     * Whether an admin has looked at the report.
     */
    public function isReviewed(): bool
    {
        return $this !== self::Open;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
