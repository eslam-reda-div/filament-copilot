<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Livewire;

use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use Filament\Facades\Filament;
use Livewire\Attributes\On;
use Livewire\Component;

class ConversationSidebar extends Component
{
    public array $conversations = [];

    public ?string $activeConversationId = null;

    public bool $isOpen = false;

    public function mount(?string $activeConversationId = null): void
    {
        $this->activeConversationId = $activeConversationId;
        $this->loadConversations();
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen) {
            $this->loadConversations();
        }
    }

    public function open(): void
    {
        $this->isOpen = true;
        $this->loadConversations();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    #[On('copilot-conversation-changed')]
    public function handleConversationChanged(?string $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->loadConversations();
    }

    #[On('copilot-refresh-sidebar')]
    public function refresh(): void
    {
        $this->loadConversations();
    }

    #[On('copilot-toggle-sidebar')]
    public function handleToggle(): void
    {
        $this->toggle();
    }

    public function selectConversation(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->dispatch('copilot-load-conversation', conversationId: $conversationId);
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

        if ($this->activeConversationId === $conversationId) {
            $this->activeConversationId = null;
            $this->dispatch('copilot-new-conversation');
        }

        $this->loadConversations();
    }

    public function newConversation(): void
    {
        $this->activeConversationId = null;
        $this->dispatch('copilot-new-conversation');
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
                'message_count' => $c->messages_count ?? $c->messages()->count(),
            ])
            ->toArray();
    }

    public function render()
    {
        return view('filament-copilot::livewire.conversation-sidebar');
    }
}
