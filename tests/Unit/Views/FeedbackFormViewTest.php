<?php

use Illuminate\Support\ViewErrorBag;

/*
 * Issue #18, phase 2: the thumbs-down form inside the chat drawer.
 */

function renderFeedbackForm(array $state = []): string
{
    $html = view('filament-copilot::components.feedback-form', array_merge([
        'feedbackMessageId' => '01JFEEDBACKMESSAGE000000000',
        'feedbackCategory' => '',
        'feedbackExpectedAnswer' => '',
        'feedbackSuggestsNewTool' => false,
        'errors' => new ViewErrorBag,
    ], $state))->render();

    return $html;
}

it('renders nothing while no reply is being reported', function () {
    expect(trim(renderFeedbackForm(['feedbackMessageId' => null])))->not->toContain('data-copilot-feedback-form');
});

it('renders every category, the expected answer and the new tool flag', function () {
    $html = renderFeedbackForm();

    expect($html)->toContain('data-copilot-feedback-form')
        ->toContain('wire:submit="submitFeedback"')
        ->toContain('wire:model="feedbackCategory"')
        ->toContain('wire:model="feedbackExpectedAnswer"')
        ->toContain('wire:model="feedbackSuggestsNewTool"')
        ->toContain('wire:click="skipFeedbackDetails"')
        ->toContain('wire:click="closeFeedbackForm"');

    foreach (['wrong_data', 'wrong_tool', 'incomplete', 'hallucinated', 'other'] as $category) {
        expect($html)->toContain('value="'.$category.'"');
    }
});

it('renders the form in german', function () {
    app()->setLocale('de');

    expect(renderFeedbackForm())->toContain('Was ist schiefgelaufen?')
        ->toContain('Falsche Daten')
        ->toContain('Wie hätte die Antwort lauten sollen?')
        ->toContain('Details überspringen')
        ->toContain('Feedback senden');
});

it('is included in the chat view', function () {
    $source = file_get_contents(__DIR__.'/../../../resources/views/livewire/copilot-chat.blade.php');

    expect($source)->toContain("@include('filament-copilot::components.feedback-form')");
});
