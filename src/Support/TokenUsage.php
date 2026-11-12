<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Support;

/**
 * Reads token counts from a laravel/ai usage object, whichever SDK
 * version produced it.
 *
 * laravel/ai 1.0 renamed Usage::$promptTokens / $completionTokens to
 * $inputTokens / $outputTokens (now including cached and reasoning tokens).
 * Reading the old names on a 1.x object silently yielded null, i.e. every
 * turn was recorded as using 0 tokens and rate / budget limits never hit.
 */
final class TokenUsage
{
    public static function input(?object $usage): int
    {
        return (int) ($usage->inputTokens ?? $usage->promptTokens ?? 0);
    }

    public static function output(?object $usage): int
    {
        return (int) ($usage->outputTokens ?? $usage->completionTokens ?? 0);
    }
}
