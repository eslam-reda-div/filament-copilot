<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Pages;

use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\CopilotFeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListCopilotFeedback extends ListRecords
{
    protected static string $resource = CopilotFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
