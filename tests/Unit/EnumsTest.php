<?php

use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;

it('has correct MessageRole cases', function () {
    expect(MessageRole::cases())->toHaveCount(4)
        ->and(MessageRole::User->value)->toBe('user')
        ->and(MessageRole::Assistant->value)->toBe('assistant')
        ->and(MessageRole::System->value)->toBe('system')
        ->and(MessageRole::Tool->value)->toBe('tool');
});

it('has correct ToolCallStatus cases', function () {
    expect(ToolCallStatus::cases())->toHaveCount(5)
        ->and(ToolCallStatus::Pending->value)->toBe('pending')
        ->and(ToolCallStatus::Approved->value)->toBe('approved')
        ->and(ToolCallStatus::Rejected->value)->toBe('rejected')
        ->and(ToolCallStatus::Executed->value)->toBe('executed')
        ->and(ToolCallStatus::Failed->value)->toBe('failed');
});

it('has all required AuditAction cases', function () {
    $actions = AuditAction::cases();

    expect($actions)->toHaveCount(30)
        ->and(AuditAction::MessageSent->value)->toBe('message_sent')
        ->and(AuditAction::RecordCreated->value)->toBe('record_created')
        ->and(AuditAction::RateLimitHit->value)->toBe('rate_limit_hit');
});
