<?php

/*
 * The streaming overlay used x-show. Alpine shows an x-show element on the
 * next animation frame (on a timer in a background tab), and nothing cancels
 * a queued show when the element is hidden in the meantime. A show still
 * queued when the turn completed (for example after the tab was hidden
 * mid-stream) ran after the hide and left an empty assistant bubble below
 * the reply until the next turn.
 */

function streamingOverlayViewSource(): string
{
    return file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');
}

function streamingOverlayOpeningTag(): string
{
    preg_match('/<div\b[^>]*\bdata-streaming-overlay\b[^>]*>/s', streamingOverlayViewSource(), $matches);

    return $matches[0] ?? '';
}

it('toggles the streaming overlay with a style binding instead of x-show', function () {
    expect(streamingOverlayOpeningTag())->not->toBe('')
        ->not->toContain('x-show')
        ->toContain("x-bind:style=\"(isStreaming || pendingComplete) && streamedContent ? '' : 'display: none'\"");
});

it('keeps the streaming overlay hidden until Alpine boots and out of attribute morphs', function () {
    expect(streamingOverlayOpeningTag())
        ->toContain('x-cloak')
        ->toContain('wire:ignore.self');
});

it('still hides the overlay as soon as the completed turn is morphed in', function () {
    expect(streamingOverlayViewSource())
        ->toContain("const overlay = this.\$el.querySelector('[data-streaming-overlay]');")
        ->toContain("if (overlay) overlay.style.display = 'none';");
});
