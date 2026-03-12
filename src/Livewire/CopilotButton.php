<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Livewire;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use Livewire\Component;

class CopilotButton extends Component
{
    public array $quickActions = [];

    public function mount(): void
    {
        try {
            $plugin = FilamentCopilotPlugin::get();
            $this->quickActions = $plugin->getQuickActions();
        } catch (\Throwable) {
            $this->quickActions = config('filament-copilot.quick_actions', []);
        }
    }

    public function openCopilot(): void
    {
        $this->dispatch('copilot-open');
    }

    public function render()
    {
        return view('filament-copilot::livewire.copilot-button');
    }
}
