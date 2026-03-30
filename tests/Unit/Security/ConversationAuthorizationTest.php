<?php

use EslamRedaDiv\FilamentCopilot\Agent\Middleware\AuditMiddleware;
use EslamRedaDiv\FilamentCopilot\Enums\AuditAction;
use EslamRedaDiv\FilamentCopilot\Http\Controllers\StreamController;
use EslamRedaDiv\FilamentCopilot\Livewire\ConversationSidebar;
use EslamRedaDiv\FilamentCopilot\Livewire\CopilotChat;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAuditLog;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Models\CopilotTokenUsage;
use EslamRedaDiv\FilamentCopilot\Services\ExportService;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

function mockFilamentContext($user, string $panelId = 'admin', $tenant = null): void
{
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

    $filamentManager = new class($guard, $panel, $tenant)
    {
        public function __construct(private $guard, private $panel, private $tenant) {}

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

it('prevents stream writes to another users conversation', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    mockFilamentContext($actor, 'admin');

    $request = Request::create('/copilot/stream', 'POST', [
        'message' => 'attack',
        'conversation_id' => $victimConversation->id,
        'panel_id' => 'admin',
    ]);

    $response = app(StreamController::class)->stream($request);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class)
        ->and(CopilotMessage::query()->where('conversation_id', $victimConversation->id)->count())->toBe(0);
});

it('prevents loading another users conversation in copilot chat', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    CopilotMessage::create([
        'conversation_id' => $victimConversation->id,
        'role' => 'user',
        'content' => 'secret',
    ]);

    mockFilamentContext($actor, 'admin');

    $chat = new CopilotChat;
    $chat->loadConversation($victimConversation->id);

    expect($chat->conversationId)->toBeNull()
        ->and($chat->messages)->toBe([]);
});

it('prevents deleting another users conversation in copilot chat', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    mockFilamentContext($actor, 'admin');

    $chat = new CopilotChat;
    $chat->deleteConversation($victimConversation->id);

    expect(CopilotConversation::query()->find($victimConversation->id))->not->toBeNull();
});

it('prevents unauthorized export from copilot chat', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    mockFilamentContext($actor, 'admin');

    $chat = new CopilotChat;
    $chat->conversationId = $victimConversation->id;

    expect($chat->exportConversation())->toBeNull();
});

it('prevents deleting another users conversation in sidebar', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    mockFilamentContext($actor, 'admin');

    $sidebar = new ConversationSidebar;
    $sidebar->deleteConversation($victimConversation->id);

    expect(CopilotConversation::query()->find($victimConversation->id))->not->toBeNull();
});

it('prevents export service from exporting another users conversation', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    CopilotMessage::create([
        'conversation_id' => $victimConversation->id,
        'role' => 'user',
        'content' => 'secret',
    ]);

    $service = app(ExportService::class);

    expect($service->toMarkdown($victimConversation->id, $actor, 'admin'))->toBeNull();
});

it('prevents export service from exporting conversation from another panel', function () {
    $user = createTestUser();

    $conversation = CopilotConversation::create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'panel_id' => 'app',
        'title' => 'App Conversation',
    ]);

    CopilotMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'hello',
    ]);

    $service = app(ExportService::class);

    expect($service->toMarkdown($conversation->id, $user, 'admin'))->toBeNull();
});

it('does not attach foreign conversation when recording token usage', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    $service = app(RateLimitService::class);

    $service->recordTokenUsage(
        user: $actor,
        panelId: 'admin',
        inputTokens: 10,
        outputTokens: 20,
        tenant: null,
        conversationId: $victimConversation->id,
        model: 'gpt-4o',
        provider: 'openai',
    );

    $usage = CopilotTokenUsage::query()->latest('created_at')->first();

    expect($usage)->not->toBeNull()
        ->and($usage->conversation_id)->toBeNull();
});

it('does not attach foreign conversation in audit logs', function () {
    $actor = createTestUser();
    $victim = createTestUser();

    $victimConversation = CopilotConversation::create([
        'participant_type' => $victim->getMorphClass(),
        'participant_id' => $victim->getKey(),
        'panel_id' => 'admin',
        'title' => 'Victim Conversation',
    ]);

    AuditMiddleware::logAction(
        action: AuditAction::MessageSent,
        user: $actor,
        panelId: 'admin',
        tenant: null,
        conversationId: $victimConversation->id,
    );

    $log = CopilotAuditLog::query()->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->conversation_id)->toBeNull();
});
