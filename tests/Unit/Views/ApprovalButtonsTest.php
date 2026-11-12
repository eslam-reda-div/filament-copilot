<?php

use Illuminate\Support\Facades\Blade;

/*
 * Issue #37: the Approve / Reject buttons used raw `bg-success-600` /
 * `bg-danger-600` utilities that no shipped stylesheet contained, so they
 * rendered as white text on a transparent background.
 */

function copilotChatViewSource(): string
{
    return file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');
}

function approvalActionsSource(): string
{
    preg_match('/<div[^>]*data-copilot-approval-actions>(.*?)<\/div>/s', copilotChatViewSource(), $matches);

    return $matches[1] ?? '';
}

it('renders the approval actions with Filament button components', function () {
    $actions = approvalActionsSource();

    expect($actions)->not->toBe('')
        ->toContain('<x-filament::button')
        ->toContain('color="success"')
        ->toContain('color="danger"')
        ->toContain("submitApprovals('approve')")
        ->toContain("submitApprovals('reject')")
        ->not->toContain('bg-success-600')
        ->not->toContain('bg-danger-600');
});

it('renders filament styled buttons that keep their alpine handlers', function () {
    $html = Blade::render(approvalActionsSource());

    expect($html)->toContain('fi-btn')
        ->toContain('fi-color-success')
        ->toContain('fi-color-danger')
        ->toContain('fi-outlined')
        ->toContain("x-on:click=\"submitApprovals('approve')\"")
        ->toContain("x-on:click=\"submitApprovals('reject')\"")
        ->toContain('x-bind:disabled="isStreaming"')
        ->toContain('type="button"');
});

it('labels the approval buttons through the translation files', function () {
    app()->setLocale('en');

    $html = Blade::render(approvalActionsSource());

    expect($html)->toContain(__('filament-copilot::filament-copilot.approve'))
        ->toContain(__('filament-copilot::filament-copilot.reject'));
});

it('ships every theme color utility the views use in the compiled stylesheet', function () {
    $css = file_get_contents(__DIR__.'/../../../resources/dist/filament-copilot.css');

    $views = collect(glob(__DIR__.'/../../../resources/views/{,*/}*.blade.php', GLOB_BRACE))
        ->map(fn (string $path) => file_get_contents($path))
        ->implode("\n");

    // Colour utilities on Filament's palette (primary/gray/danger/...),
    // optionally behind variants (`dark:`, `hover:`) and with an opacity.
    preg_match_all(
        '/(?<![\w:\/-])((?:[a-z-]+:)*(?:bg|text|border|ring|from|to|placeholder|divide)-(?:primary|gray|danger|success|warning|info)-\d{2,3}(?:\/\d{1,3})?)(?![\w\/-])/',
        $views,
        $matches,
    );

    $missing = collect($matches[1])
        ->unique()
        ->reject(function (string $class) use ($css) {
            $selector = '.'.addcslashes($class, ':/.');

            return str_contains($css, $selector);
        })
        ->values()
        ->all();

    expect($missing)->toBe([], 'Rebuild resources/dist with `npm run build`; missing: '.implode(', ', $missing));
});
