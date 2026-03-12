<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Agent;

use EslamRedaDiv\FilamentCopilot\Discovery\PageInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\ResourceInspector;
use EslamRedaDiv\FilamentCopilot\Discovery\WidgetInspector;
use EslamRedaDiv\FilamentCopilot\Models\CopilotAgentMemory;
use Illuminate\Database\Eloquent\Model;

class ContextBuilder
{
    protected string $panelId;

    protected ?Model $tenant = null;

    protected Model $user;

    protected ?string $customPrompt = null;

    public function __construct(
        protected ResourceInspector $resourceInspector,
        protected PageInspector $pageInspector,
        protected WidgetInspector $widgetInspector,
    ) {}

    public function forPanel(string $panelId): static
    {
        $this->panelId = $panelId;

        return $this;
    }

    public function forTenant(?Model $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function forUser(Model $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function withCustomPrompt(?string $prompt): static
    {
        $this->customPrompt = $prompt;

        return $this;
    }

    public function build(): string
    {
        $sections = [];

        $sections[] = $this->buildBasePrompt();
        $sections[] = $this->buildResourceContext();
        $sections[] = $this->buildPageContext();
        $sections[] = $this->buildWidgetContext();
        $sections[] = $this->buildMemoryContext();

        if ($this->customPrompt) {
            $sections[] = "## Additional Instructions\n{$this->customPrompt}";
        }

        return implode("\n\n", array_filter($sections));
    }

    protected function buildBasePrompt(): string
    {
        $prompt = config('filament-copilot.system_prompt');

        if ($prompt) {
            return $prompt;
        }

        return <<<'PROMPT'
You are a helpful Filament admin panel assistant. You help users manage their data
and navigate the admin panel efficiently.

## Guidelines
- Always respect user permissions. Never perform actions the user is not authorized for.
- When modifying data, confirm the changes before saving unless specifically asked to save.
- Provide clear, concise responses.
- If an action fails, explain why and suggest alternatives.

## How to Work with Resources, Pages, and Widgets
You have global tools for discovery and execution:
- **list_resources** / **list_pages** / **list_widgets**  see what is available
- **get_tools**  discover the copilot tools available for a specific resource, page, or widget
- **run_tool**  execute a discovered tool with the required arguments

### Workflow
1. The system prompt below lists all available resources, pages, and widgets with their descriptions.
2. When you need to perform an action on a resource (e.g. list products), use **get_tools** with the resource class to discover available tools.
3. Use **run_tool** to execute the specific tool with the required arguments.
4. If a tool requires confirmation (needToAsk), always ask the user first.

## Confirmation Rules
- Tools that return "CONFIRMATION REQUIRED" need explicit user confirmation before re-executing.
- Always ask the user before destructive operations (delete, force delete).

## Memory
- Use **remember** / **recall** to store and retrieve user preferences across conversations.
PROMPT;
    }

    protected function buildResourceContext(): string
    {
        return $this->resourceInspector->buildResourceContext($this->panelId);
    }

    protected function buildPageContext(): string
    {
        return $this->pageInspector->buildPageContext($this->panelId);
    }

    protected function buildWidgetContext(): string
    {
        return $this->widgetInspector->buildWidgetContext($this->panelId);
    }

    protected function buildMemoryContext(): string
    {
        if (! config('filament-copilot.memory.enabled', true)) {
            return '';
        }

        $memories = CopilotAgentMemory::recallAll(
            $this->user,
            $this->panelId,
            $this->tenant,
        );

        if (empty($memories)) {
            return '';
        }

        $lines = ['## Your Memories About This User'];
        foreach ($memories as $key => $value) {
            $lines[] = "- {$key}: {$value}";
        }

        return implode("\n", $lines);
    }

}
