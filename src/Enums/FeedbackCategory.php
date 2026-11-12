<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a user says went wrong with a reply they rated thumbs down.
 */
enum FeedbackCategory: string implements HasColor, HasLabel
{
    case WrongData = 'wrong_data';
    case WrongTool = 'wrong_tool';
    case Incomplete = 'incomplete';
    case Hallucinated = 'hallucinated';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("filament-copilot::filament-copilot.feedback_category_{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::WrongData, self::Hallucinated => 'danger',
            self::WrongTool => 'warning',
            self::Incomplete => 'info',
            self::Other => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->getLabel()])
            ->all();
    }
}
