<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Pages;

use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Actions\ReviewFeedbackAction;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\CopilotFeedbackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCopilotFeedback extends ViewRecord
{
    protected static string $resource = CopilotFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReviewFeedbackAction::make(),
            DeleteAction::make(),
        ];
    }
}
