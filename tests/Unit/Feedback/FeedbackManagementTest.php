<?php

use EslamRedaDiv\FilamentCopilot\Enums\FeedbackCategory;
use EslamRedaDiv\FilamentCopilot\Enums\FeedbackStatus;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotFeedback;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Pages\CopilotDashboardPage;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Actions\ReviewFeedbackAction;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\CopilotFeedbackResource;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Schemas\CopilotFeedbackInfolist;
use EslamRedaDiv\FilamentCopilot\Resources\CopilotFeedback\Tables\CopilotFeedbackTable;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Widgets\CopilotFeedbackOverview;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/*
 * Issue #18, phase 2: the admin side — a Feedback resource with a review
 * workflow, and a feedback summary on the dashboard.
 */

function feedbackReport(array $attributes = []): CopilotFeedback
{
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    $manager->addUserMessage($conversation, 'How many orders shipped today?');
    $reply = $manager->addAssistantMessage($conversation, 'Twelve.');
    $reply->update(['rating' => MessageRating::Negative]);

    return CopilotFeedback::query()->create(array_merge([
        'message_id' => $reply->id,
        'conversation_id' => $conversation->id,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'panel_id' => 'admin',
        'category' => FeedbackCategory::WrongData,
    ], $attributes));
}

it('registers the feedback resource with the management resources', function () {
    config(['filament-copilot.management.enabled' => true]);

    $panel = Panel::make()->id('feedback-management');
    FilamentCopilotPlugin::make()->register($panel);

    expect($panel->getResources())->toContain(CopilotFeedbackResource::class);
});

it('does not register the feedback resource while management is disabled', function () {
    config(['filament-copilot.management.enabled' => false]);

    $panel = Panel::make()->id('feedback-management-off');
    FilamentCopilotPlugin::make()->register($panel);

    expect($panel->getResources())->not->toContain(CopilotFeedbackResource::class);
});

it('badges the navigation with the number of open reports', function () {
    expect(CopilotFeedbackResource::getNavigationBadge())->toBeNull();

    feedbackReport();
    feedbackReport();
    feedbackReport()->review(FeedbackStatus::Resolved);

    expect(CopilotFeedbackResource::getNavigationBadge())->toBe('2')
        ->and(CopilotFeedbackResource::canCreate())->toBeFalse()
        ->and(array_keys(CopilotFeedbackResource::getPages()))->toBe(['index', 'view']);
});

it('records a review with the reviewer and time, and clears them when reopened', function () {
    $admin = createTestUser();
    $feedback = feedbackReport();

    $feedback->review(FeedbackStatus::Resolved, $admin, 'Added a shipping tool.');

    expect($feedback->fresh())
        ->status->toBe(FeedbackStatus::Resolved)
        ->admin_notes->toBe('Added a shipping tool.')
        ->reviewer_id->toBe($admin->getKey())
        ->reviewed_at->not->toBeNull();

    $feedback->review(FeedbackStatus::Open, $admin, 'Reopened.');

    expect($feedback->fresh())
        ->status->toBe(FeedbackStatus::Open)
        ->reviewer_id->toBeNull()
        ->reviewed_at->toBeNull()
        ->and($feedback->isReviewed())->toBeFalse();
});

it('shows the user message the rated reply answered', function () {
    $feedback = feedbackReport();

    expect($feedback->promptMessage()?->content)->toBe('How many orders shipped today?')
        ->and($feedback->message->feedback->is($feedback))->toBeTrue()
        ->and($feedback->conversation->feedback)->toHaveCount(1);
});

it('deletes reports with their message', function () {
    // Like every other copilot table, the cascade lives in the database;
    // Laravel's default SQLite config enforces foreign keys too.
    DB::statement('PRAGMA foreign_keys = ON');

    $feedback = feedbackReport();

    CopilotMessage::query()->whereKey($feedback->message_id)->delete();

    expect(CopilotFeedback::query()->count())->toBe(0);
});

it('configures the feedback table with its columns, filters and review actions', function () {
    $table = CopilotFeedbackTable::configure(Table::make(Mockery::mock(HasTable::class)));

    expect(array_keys($table->getColumns()))->toBe([
        'created_at', 'category', 'message.content', 'expected_answer', 'suggests_new_tool',
        'status', 'conversation.title', 'panel_id', 'participant_id', 'reviewed_at',
    ])->and(array_keys($table->getFilters()))->toBe(['status', 'category', 'suggests_new_tool', 'panel_id']);
});

it('configures the feedback infolist', function () {
    $schema = CopilotFeedbackInfolist::configure(Schema::make());

    expect($schema->getComponents())->toHaveCount(3);
});

it('builds the review actions', function () {
    expect(ReviewFeedbackAction::make()->getName())->toBe('review')
        ->and(ReviewFeedbackAction::bulk(FeedbackStatus::Resolved)->getName())->toBe('mark_resolved')
        ->and(ReviewFeedbackAction::bulk(FeedbackStatus::Dismissed)->getName())->toBe('mark_dismissed');
});

it('summarises ratings and open reports for the dashboard', function () {
    expect(CopilotFeedbackOverview::summary())->toBe(['positive' => 0, 'negative' => 0, 'satisfaction' => null, 'open' => 0]);

    feedbackReport(); // one negative rating + an open report
    $user = createTestUser();
    $manager = app(ConversationManager::class);
    $conversation = $manager->create($user, 'admin');
    foreach (range(1, 3) as $i) {
        $manager->addAssistantMessage($conversation, "Good answer {$i}")->update(['rating' => MessageRating::Positive]);
    }

    // Ratings older than the period are left out.
    $old = $manager->addAssistantMessage($conversation, 'Old answer');
    $old->forceFill(['rating' => MessageRating::Negative, 'created_at' => now()->subDays(45)])->save();

    expect(CopilotFeedbackOverview::summary())->toBe(['positive' => 3, 'negative' => 1, 'satisfaction' => 75, 'open' => 1]);
});

it('shows the feedback summary on the copilot dashboard', function () {
    $widgets = (fn () => $this->getHeaderWidgets())->call(new CopilotDashboardPage);

    expect($widgets)->toContain(CopilotFeedbackOverview::class);
});

it('labels categories and statuses in the current locale', function () {
    app()->setLocale('de');

    expect(FeedbackCategory::Hallucinated->getLabel())->toBe('Etwas erfunden')
        ->and(FeedbackStatus::InReview->getLabel())->toBe('In Prüfung')
        ->and(FeedbackStatus::Open->isReviewed())->toBeFalse()
        ->and(FeedbackStatus::Dismissed->isReviewed())->toBeTrue()
        ->and(array_keys(FeedbackCategory::options()))->toBe(['wrong_data', 'wrong_tool', 'incomplete', 'hallucinated', 'other']);
});
