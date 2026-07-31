<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Livewire;

use EslamRedaDiv\FilamentCopilot\Enums\MessageRating;
use EslamRedaDiv\FilamentCopilot\Enums\MessageRole;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Services\ExportService;
use Filament\Facades\Filament;
use Livewire\Attributes\On;
use Livewire\Component;

class CopilotChat extends Component
{
    public bool $isOpen = false;

    public ?string $conversationId = null;

    public string $message = '';

    public array $messages = [];

    public bool $isLoading = false;

    public ?string $pendingToolCallId = null;

    public array $conversations = [];

    public array $quickActions = [];

    public function mount(): void
    {
        $this->loadConversations();

        try {
            $plugin = FilamentCopilotPlugin::get();
            $this->quickActions = $plugin->getQuickActions();
        } catch (\Throwable) {
            $this->quickActions = config('filament-copilot.quick_actions', []);
        }
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function getStreamUrl(): string
    {
        return route('filament-copilot.stream');
    }

    public function sendMessage(): void
    {
        $content = trim($this->message);

        if ($content === '') {
            return;
        }

        $this->message = '';

        $this->messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        $this->dispatch('copilot-send-stream', [
            'message' => $content,
            'conversationId' => $this->conversationId,
            'panelId' => Filament::getCurrentPanel()?->getId(),
            'streamUrl' => $this->getStreamUrl(),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * Called from JavaScript after SSE streaming completes.
     *
     * `$messageId` is the id `StreamController` persisted for this reply and
     * echoed back in the SSE `done` payload. It is optional so a published copy
     * of the chat view that predates that payload keeps working — the reply is
     * then simply rendered without feedback buttons until the conversation is
     * reloaded.
     */
    #[On('copilot-stream-complete')]
    public function handleStreamComplete(string $content, ?string $newConversationId = null, ?array $toolCalls = null, ?string $messageId = null): void
    {
        if ($newConversationId && ! $this->conversationId) {
            $this->conversationId = $newConversationId;
        }

        // Add tool calls as collapsible messages
        if ($toolCalls) {
            foreach ($toolCalls as $toolCall) {
                $this->messages[] = [
                    'role' => 'tool_call',
                    'tool_name' => $toolCall['name'] ?? 'Tool',
                    'arguments' => $toolCall['arguments'] ?? null,
                    'result' => $toolCall['result'] ?? null,
                    'success' => $toolCall['status'] === 'done',
                    'error' => $toolCall['error'] ?? null,
                    'content' => $toolCall['result'] ?? '',
                ];
            }
        }

        $assistantMessage = [
            'role' => 'assistant',
            'content' => $content,
        ];

        // Not trusted as proof of ownership — it only decides whether the
        // buttons render. Every write still goes through submitRating(), which
        // re-resolves the message against the current participant.
        if ($messageId !== null) {
            $assistantMessage['id'] = $messageId;
            $assistantMessage['rating'] = null;
        }

        $this->messages[] = $assistantMessage;

        $this->loadConversations();
    }

    /**
     * Record (or clear) a thumbs up/down on one assistant message.
     *
     * Ownership is resolved in the database before anything the caller sent is
     * validated: a message belonging to somebody else's conversation must not
     * even reveal that it exists, so every rejection is the same silent no-op.
     */
    public function submitRating(string $messageId, string $rating): void
    {
        if (! config('filament-copilot.feedback.enabled', true)) {
            return;
        }

        $user = Filament::auth()->user();
        $panelId = Filament::getCurrentPanel()?->getId();

        if (! $user || ! $panelId) {
            return;
        }

        $tenant = Filament::getTenant();

        $message = CopilotMessage::query()
            ->whereKey($messageId)
            ->whereHas('conversation', fn ($query) => $query
                ->forPanel($panelId)
                ->forParticipant($user)
                ->forTenant($tenant))
            ->first();

        if (! $message) {
            return;
        }

        $rating = MessageRating::tryFrom($rating);

        if (! $rating) {
            return;
        }

        // Only the copilot's own replies are ratable — never the user's own
        // text, system notices or tool output.
        if ($message->role !== MessageRole::Assistant) {
            return;
        }

        // Clicking the thumb that is already lit clears the rating.
        $message->update([
            'rating' => $message->rating === $rating ? null : $rating,
        ]);

        $this->applyRatingToMessages($messageId, $message->rating?->value);
    }

    /**
     * Reflect a new rating in the component state so the thumb lights up
     * without reloading the conversation.
     */
    protected function applyRatingToMessages(string $messageId, ?string $rating): void
    {
        foreach ($this->messages as $index => $message) {
            if (($message['id'] ?? null) === $messageId) {
                $this->messages[$index]['rating'] = $rating;

                return;
            }
        }
    }

    /**
     * Called from JavaScript when SSE streaming encounters an error.
     */
    #[On('copilot-stream-error')]
    public function handleStreamError(string $error): void
    {
        $this->messages[] = [
            'role' => 'system',
            'content' => __('filament-copilot::filament-copilot.error_occurred').': '.$error,
        ];
    }

    protected function getConversationMessages($conversation): array
    {
        /** @var ConversationManager $conversationManager */
        $conversationManager = app(ConversationManager::class);

        return $conversationManager->getMessagesForChat($conversation);
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->messages = [];
        $this->pendingToolCallId = null;
        $this->dispatch('copilot-conversation-changed', conversationId: null);
    }

    #[On('copilot-load-conversation')]
    public function loadConversation(string $conversationId): void
    {
        $user = Filament::auth()->user();
        $panelId = Filament::getCurrentPanel()?->getId();

        if (! $user || ! $panelId) {
            return;
        }

        $tenant = Filament::getTenant();

        $conversation = CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->find($conversationId);

        if (! $conversation) {
            return;
        }

        $this->conversationId = $conversationId;
        $this->messages = $this->getConversationMessages($conversation);
        $this->dispatch('copilot-conversation-changed', conversationId: $conversationId);
    }

    public function deleteConversation(string $conversationId): void
    {
        /** @var ConversationManager $conversationManager */
        $conversationManager = app(ConversationManager::class);
        $user = Filament::auth()->user();
        $panelId = Filament::getCurrentPanel()?->getId();

        if (! $user || ! $panelId) {
            return;
        }

        $tenant = Filament::getTenant();

        $conversation = CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->find($conversationId);

        if ($conversation) {
            $conversationManager->delete($conversation);
        }

        if ($this->conversationId === $conversationId) {
            $this->newConversation();
        }

        $this->loadConversations();
    }

    public function toggleHistory(): void
    {
        $this->dispatch('copilot-toggle-sidebar');
    }

    protected function loadConversations(): void
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return;
        }

        $panelId = Filament::getCurrentPanel()?->getId();
        $tenant = Filament::getTenant();

        if (! $panelId) {
            return;
        }

        /** @var ConversationManager $conversationManager */
        $conversationManager = app(ConversationManager::class);

        $this->conversations = $conversationManager
            ->getConversations($user, $panelId, $tenant)
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'updated_at' => $c->updated_at->diffForHumans(),
            ])
            ->toArray();
    }

    #[On('copilot-quick-action')]
    public function handleQuickAction(string $prompt): void
    {
        $this->message = $prompt;
        $this->sendMessage();
    }

    public function exportConversation(): \Symfony\Component\HttpFoundation\StreamedResponse|null
    {
        if (! $this->conversationId) {
            return null;
        }

        $user = Filament::auth()->user();
        $panelId = Filament::getCurrentPanel()?->getId();

        if (! $user || ! $panelId) {
            return null;
        }

        $tenant = Filament::getTenant();

        $conversation = CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->find($this->conversationId);

        if (! $conversation) {
            return null;
        }

        /** @var ExportService $exportService */
        $exportService = app(ExportService::class);
        $markdown = $exportService->toMarkdown($this->conversationId, $user, $panelId, $tenant);

        if (! $markdown) {
            return null;
        }

        $filename = 'copilot-conversation-'.now()->format('Y-m-d-His').'.md';

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, $filename, ['Content-Type' => 'text/markdown']);
    }

    public function render()
    {
        return view('filament-copilot::livewire.copilot-chat');
    }
}
