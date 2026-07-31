<?php

function renderChatMessage(array $msg): string
{
    return view('filament-copilot::components.chat-message', ['msg' => $msg])->render();
}

it('renders feedback buttons on a persisted assistant message', function () {
    $html = renderChatMessage([
        'role' => 'assistant',
        'content' => 'Revenue is $1,200 this month.',
        'id' => '01hqxk8k0000000000000000',
        'rating' => null,
    ]);

    expect($html)->toContain("submitRating('01hqxk8k0000000000000000', 'positive')")
        ->toContain("submitRating('01hqxk8k0000000000000000', 'negative')")
        ->toContain('Helpful')
        ->toContain('Not helpful')
        ->toContain('aria-pressed="false"');
});

it('marks the submitted rating as pressed', function () {
    $html = renderChatMessage([
        'role' => 'assistant',
        'content' => 'Revenue is $1,200 this month.',
        'id' => '01hqxk8k0000000000000000',
        'rating' => 'negative',
    ]);

    expect($html)->toContain('Remove unhelpful rating')
        ->toContain('aria-pressed="true"')
        ->not->toContain('Remove helpful rating');
});

it('renders no feedback buttons on a message without an id', function () {
    $html = renderChatMessage([
        'role' => 'assistant',
        'content' => 'Revenue is $1,200 this month.',
    ]);

    expect($html)->not->toContain('submitRating');
});

it('renders no feedback buttons while message feedback is disabled', function () {
    config()->set('filament-copilot.feedback.enabled', false);

    $html = renderChatMessage([
        'role' => 'assistant',
        'content' => 'Revenue is $1,200 this month.',
        'id' => '01hqxk8k0000000000000000',
        'rating' => 'positive',
    ]);

    expect($html)->not->toContain('submitRating');
});

it('renders no feedback buttons on a user message', function () {
    $html = renderChatMessage([
        'role' => 'user',
        'content' => 'What is the total revenue?',
        'id' => '01hqxk8k0000000000000000',
        'rating' => null,
    ]);

    expect($html)->not->toContain('submitRating');
});
