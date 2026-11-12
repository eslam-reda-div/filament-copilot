<?php

use EslamRedaDiv\FilamentCopilot\Enums\FeedbackCategory;
use EslamRedaDiv\FilamentCopilot\Enums\FeedbackStatus;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Events\CopilotFeedbackSubmitted;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/*
 * Issue #18, phase 2: a thumbs down can open a short form (category, the
 * expected answer, "needs a new tool"), whose reports admins review.
 */

function feedbackReplyFor($user, string $panelId = 'admin'): CopilotMessage
{
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, $panelId);
    $manager->addUserMessage($conversation, 'How many orders shipped today?');

    return $manager->addAssistantMessage($conversation, 'Twelve orders shipped today.');
}

function feedbackChat(CopilotMessage $reply)
{
    $chat = copilotTestRecordingChat();
    $chat->messages = [['role' => 'assistant', 'content' => $reply->content, 'id' => $reply->id, 'rating' => null]];

    return $chat;
}

beforeEach(function () {
    config()->set('filament-copilot.feedback.detailed', true);

    $this->user = createTestUser();
    copilotTestSwapFilament($this->user);
    $this->reply = feedbackReplyFor($this->user);
});

it('keeps thumbs down one click while detailed feedback is off', function () {
    config()->set('filament-copilot.feedback.detailed', false);

    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');

    expect($chat->feedbackMessageId)->toBeNull()
        ->and($this->reply->fresh()->rating)->toBe(MessageRating::Negative)
        ->and(CopilotFeedback::query()->count())->toBe(0);
});

it('opens the form on thumbs down instead of rating right away', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');

    expect($chat->feedbackMessageId)->toBe($this->reply->id)
        ->and($this->reply->fresh()->rating)->toBeNull();
});

it('keeps thumbs up one click', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'positive');

    expect($chat->feedbackMessageId)->toBeNull()
        ->and($this->reply->fresh()->rating)->toBe(MessageRating::Positive);
});

it('stores a report and the negative rating when the form is sent', function () {
    Event::fake([CopilotFeedbackSubmitted::class]);

    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = 'wrong_data';
    $chat->feedbackExpectedAnswer = '  Fourteen orders shipped.  ';
    $chat->feedbackSuggestsNewTool = true;
    $chat->submitFeedback();

    $feedback = CopilotFeedback::query()->sole();

    expect($feedback->message_id)->toBe($this->reply->id)
        ->and($feedback->conversation_id)->toBe($this->reply->conversation_id)
        ->and($feedback->category)->toBe(FeedbackCategory::WrongData)
        ->and($feedback->expected_answer)->toBe('Fourteen orders shipped.')
        ->and($feedback->suggests_new_tool)->toBeTrue()
        ->and($feedback->status)->toBe(FeedbackStatus::Open)
        ->and($feedback->panel_id)->toBe('admin')
        ->and($feedback->participant_id)->toBe($this->user->getKey())
        ->and($this->reply->fresh()->rating)->toBe(MessageRating::Negative)
        ->and($chat->feedbackMessageId)->toBeNull()
        ->and($chat->messages[0]['rating'])->toBe('negative');

    Event::assertDispatched(CopilotFeedbackSubmitted::class, fn ($event) => $event->feedback->is($feedback));
});

it('requires a valid category', function (string $category) {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = $category;

    expect(fn () => $chat->submitFeedback())->toThrow(ValidationException::class);
    expect(CopilotFeedback::query()->count())->toBe(0)
        ->and($this->reply->fresh()->rating)->toBeNull();
})->with(['missing' => [''], 'unknown' => ['rude']]);

it('limits the expected answer length', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = 'other';
    $chat->feedbackExpectedAnswer = str_repeat('a', 5001);

    expect(fn () => $chat->submitFeedback())->toThrow(ValidationException::class);
});

it('records only the rating when the details are skipped', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->skipFeedbackDetails();

    expect($this->reply->fresh()->rating)->toBe(MessageRating::Negative)
        ->and(CopilotFeedback::query()->count())->toBe(0)
        ->and($chat->feedbackMessageId)->toBeNull();
});

it('records nothing when the form is closed', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->closeFeedbackForm();

    expect($this->reply->fresh()->rating)->toBeNull()
        ->and(CopilotFeedback::query()->count())->toBe(0);
});

it('never opens the form for somebody else\'s reply', function () {
    $otherReply = feedbackReplyFor(createTestUser());

    $chat = feedbackChat($this->reply);
    $chat->submitRating($otherReply->id, 'negative');

    expect($chat->feedbackMessageId)->toBeNull();
});

it('re-checks ownership when the form is sent', function () {
    $otherReply = feedbackReplyFor(createTestUser());

    $chat = feedbackChat($this->reply);
    // The id round-trips through the browser and could be swapped.
    $chat->feedbackMessageId = $otherReply->id;
    $chat->feedbackCategory = 'other';
    $chat->submitFeedback();

    expect(CopilotFeedback::query()->count())->toBe(0)
        ->and($otherReply->fresh()->rating)->toBeNull()
        ->and($chat->feedbackMessageId)->toBeNull();
});

it('refuses detailed feedback while feedback is disabled', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');

    config()->set('filament-copilot.feedback.enabled', false);
    $chat->feedbackCategory = 'other';
    $chat->submitFeedback();

    expect(CopilotFeedback::query()->count())->toBe(0);
});

it('withdraws an unreviewed report when the thumbs down is cleared or flipped', function (string $next) {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = 'incomplete';
    $chat->submitFeedback();

    $chat->submitRating($this->reply->id, $next);

    expect(CopilotFeedback::query()->count())->toBe(0);
})->with(['cleared' => ['negative'], 'flipped to thumbs up' => ['positive']]);

it('keeps a report an admin already reviewed', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = 'incomplete';
    $chat->submitFeedback();

    CopilotFeedback::query()->sole()->review(FeedbackStatus::Resolved, createTestUser(), 'Fixed the prompt.');

    $chat->submitRating($this->reply->id, 'negative'); // clear

    expect(CopilotFeedback::query()->sole()->status)->toBe(FeedbackStatus::Resolved)
        ->and($this->reply->fresh()->rating)->toBeNull();
});

it('updates the one report per reply and reopens it when resubmitted', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->feedbackCategory = 'incomplete';
    $chat->submitFeedback();

    CopilotFeedback::query()->sole()->review(FeedbackStatus::Dismissed, createTestUser(), 'Works as intended.');

    // Clearing keeps the reviewed report; rating thumbs down again prefills it.
    $chat->submitRating($this->reply->id, 'negative');
    $chat->submitRating($this->reply->id, 'negative');

    expect($chat->feedbackCategory)->toBe('incomplete');

    $chat->feedbackCategory = 'hallucinated';
    $chat->submitFeedback();

    $feedback = CopilotFeedback::query()->sole();

    expect($feedback->category)->toBe(FeedbackCategory::Hallucinated)
        ->and($feedback->status)->toBe(FeedbackStatus::Open)
        ->and($feedback->reviewed_at)->toBeNull()
        // The admin's notes stay for the record.
        ->and($feedback->admin_notes)->toBe('Works as intended.');
});

it('closes the form when the conversation changes', function () {
    $chat = feedbackChat($this->reply);
    $chat->submitRating($this->reply->id, 'negative');
    $chat->newConversation();

    expect($chat->feedbackMessageId)->toBeNull();
});
