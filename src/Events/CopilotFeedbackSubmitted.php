<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Events;

use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user submitted (or updated) a detailed report on a thumbs-down reply —
 * e.g. to notify the team or open a ticket.
 */
class CopilotFeedbackSubmitted
{
    use Dispatchable;

    public function __construct(
        public CopilotFeedback $feedback,
    ) {}
}
