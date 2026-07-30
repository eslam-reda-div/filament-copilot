<?php

use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Livewire\CopilotChat;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use Filament\Facades\Filament;

function swapFilamentForFeedbackTest($user, string $panelId = 'admin', $tenant = null): void
{
    $plugin = new FilamentCopilotPlugin;

    $panel = new class($panelId)
    {
        public function __construct(private string $id) {}

        public function getId(): string
        {
            return $this->id;
        }
    };

    $guard = new class($user)
    {
        public function __construct(private $user) {}

        public function user()
        {
            return $this->user;
        }
    };

    $filamentManager = new class($guard, $panel, $tenant, $plugin)
    {
        public function __construct(private $guard, private $panel, private $tenant, private FilamentCopilotPlugin $plugin) {}

        public function getPlugin(string $pluginId): FilamentCopilotPlugin
        {
            return $this->plugin;
        }

        public function setCurrentPanel(string $panelId): void {}

        public function auth()
        {
            return $this->guard;
        }

        public function getCurrentPanel()
        {
            return $this->panel;
        }

        public function getTenant()
        {
            return $this->tenant;
        }
    };

    app()->instance('filament', $filamentManager);
    Filament::swap($filamentManager);
}

function assistantMessageFor($user, string $panelId = 'admin'): CopilotMessage
{
    /** @var ConversationManager $manager */
    $manager = app(ConversationManager::class);

    $conversation = $manager->create($user, $panelId);
    $manager->addUserMessage($conversation, 'What is the total revenue?');

    return $manager->addAssistantMessage($conversation, 'Revenue is $1,200 this month.');
}

it('records a rating on an assistant message', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'negative');

    expect($message->fresh()->rating)->toBe(MessageRating::Negative);
});

it('clears the rating when the same thumb is submitted twice', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'positive');
    $chat->submitRating($message->id, 'positive');

    expect($message->fresh()->rating)->toBeNull();
});

it('replaces the rating when the opposite thumb is submitted', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'negative');
    $chat->submitRating($message->id, 'positive');

    expect($message->fresh()->rating)->toBe(MessageRating::Positive);
});

it('reflects the rating in the loaded chat messages', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->loadConversation($message->conversation_id);
    $chat->submitRating($message->id, 'positive');

    $rated = collect($chat->messages)->firstWhere('id', $message->id);

    expect($rated['rating'])->toBe('positive');
});

it('rejects an unknown rating value', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'brilliant');

    expect($message->fresh()->rating)->toBeNull();
});

it('refuses to rate a message that is not from the assistant', function () {
    $user = createTestUser();
    $assistantMessage = assistantMessageFor($user);

    $userMessage = CopilotMessage::query()
        ->where('conversation_id', $assistantMessage->conversation_id)
        ->where('role', MessageRole::User->value)
        ->firstOrFail();

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($userMessage->id, 'positive');

    expect($userMessage->fresh()->rating)->toBeNull();
});

it('refuses to rate a message in another users conversation', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $message = assistantMessageFor($victim);

    swapFilamentForFeedbackTest($actor);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'negative');

    expect($message->fresh()->rating)->toBeNull();
});

it('refuses to rate a message from another panel', function () {
    $user = createTestUser();

    $message = assistantMessageFor($user, 'app');

    swapFilamentForFeedbackTest($user, 'admin');

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'negative');

    expect($message->fresh()->rating)->toBeNull();
});

it('refuses to rate when there is no authenticated user', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest(null);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'negative');

    expect($message->fresh()->rating)->toBeNull();
});

it('refuses to rate while message feedback is disabled', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    config()->set('filament-copilot.feedback.enabled', false);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->submitRating($message->id, 'positive');

    expect($message->fresh()->rating)->toBeNull();
});

it('attaches the streamed message id to the pushed assistant message', function () {
    $user = createTestUser();
    $message = assistantMessageFor($user);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->handleStreamComplete(
        content: 'Revenue is $1,200 this month.',
        newConversationId: $message->conversation_id,
        toolCalls: null,
        messageId: $message->id,
    );

    $pushed = end($chat->messages);

    expect($pushed['role'])->toBe('assistant')
        ->and($pushed['id'])->toBe($message->id)
        ->and($pushed['rating'])->toBeNull();

    // ...and the reply is immediately ratable.
    $chat->submitRating($pushed['id'], 'positive');

    expect($message->fresh()->rating)->toBe(MessageRating::Positive);
});

it('pushes the assistant message without an id when the stream sends none', function () {
    $user = createTestUser();
    $conversation = CopilotConversation::create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'panel_id' => 'admin',
        'title' => 'Legacy Published View',
    ]);

    swapFilamentForFeedbackTest($user);

    $chat = new CopilotChat;
    $chat->handleStreamComplete('Hello there', $conversation->id);

    $pushed = end($chat->messages);

    expect($pushed)->toBe([
        'role' => 'assistant',
        'content' => 'Hello there',
    ]);
});
