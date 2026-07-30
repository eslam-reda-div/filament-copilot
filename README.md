# Filament Copilot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eslam-reda-div/filament-copilot.svg?style=flat-square)](https://packagist.org/packages/eslam-reda-div/filament-copilot)
[![Total Downloads](https://img.shields.io/packagist/dt/eslam-reda-div/filament-copilot.svg?style=flat-square)](https://packagist.org/packages/eslam-reda-div/filament-copilot)
[![License](https://img.shields.io/packagist/l/eslam-reda-div/filament-copilot.svg?style=flat-square)](LICENSE.md)

**An AI-powered copilot plugin for FilamentPHP v5.** Give your admin panel a built-in AI assistant that understands your resources, pages, and widgets — powered by the official [Laravel AI SDK](https://github.com/laravel/ai).

Filament Copilot integrates directly into your Filament panels with a chat interface, real-time SSE streaming, tool execution, conversation history, agent memory, audit logging, rate limiting, token budget tracking, and a full management dashboard.

---

## Table of Contents

- [Screenshots](#screenshots)
- [Features](#features)
- [Compatibility](#compatibility)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Step 1 — Install via Composer](#step-1--install-via-composer)
  - [Step 2 — Run the Install Command](#step-2--run-the-install-command)
  - [Step 3 — Manual Installation (Alternative)](#step-3--manual-installation-alternative)
- [Configuration](#configuration)
  - [AI Provider & Model](#ai-provider--model)
  - [Rate Limiting](#rate-limiting)
  - [Token Budget](#token-budget)
  - [Audit Logging](#audit-logging)
  - [Agent Memory](#agent-memory)
  - [Management Dashboard](#management-dashboard)
  - [Message Feedback](#message-feedback)
  - [Quick Actions](#quick-actions)
  - [System Prompt](#system-prompt)
  - [Global Tools](#global-tools)
- [Panel Registration](#panel-registration)
  - [Basic Registration](#basic-registration)
  - [Full Configuration via Plugin API](#full-configuration-via-plugin-api)
- [Making Resources Copilot-Enabled](#making-resources-copilot-enabled)
- [Making Pages Copilot-Enabled](#making-pages-copilot-enabled)
- [Making Widgets Copilot-Enabled](#making-widgets-copilot-enabled)
- [Creating Copilot Tools](#creating-copilot-tools)
  - [Using the Generator Command](#using-the-generator-command)
  - [Tool Templates](#tool-templates)
  - [Writing a Custom Tool](#writing-a-custom-tool)
  - [Folder Structure](#folder-structure)
  - [Registering Tools](#registering-tools)
- [Built-in Tools](#built-in-tools)
- [User Model Setup](#user-model-setup)
- [How It Works](#how-it-works)
  - [Architecture](#architecture)
  - [Streaming](#streaming)
  - [Agent & Context Building](#agent--context-building)
  - [Tool Execution Flow](#tool-execution-flow)
- [Events](#events)
- [Models & Database](#models--database)
- [Management Dashboard](#management-dashboard-1)
- [Customization](#customization)
  - [Custom System Prompt](#custom-system-prompt)
  - [Authorization](#authorization)
  - [Translations](#translations)
  - [Views](#views)
  - [Publishing Stubs](#publishing-stubs)
- [Testing](#testing)
- [Supported AI Providers](#supported-ai-providers)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

---

## Screenshots

<p align="center">
  <img src="screenshot-1.png" alt="Filament Copilot Screenshot 1" width="100%">
</p>

<p align="center">
  <img src="screenshot-2.png" alt="Filament Copilot Screenshot 2" width="100%">
</p>

<p align="center">
  <img src="screenshot-3.png" alt="Filament Copilot Screenshot 3" width="100%">
</p>

<p align="center">
  <img src="screenshot-4.png" alt="Filament Copilot Screenshot 4" width="100%">
</p>

<p align="center">
  <img src="screenshot-5.png" alt="Filament Copilot Screenshot 5" width="100%">
</p>

<p align="center">
  <img src="screenshot-6.png" alt="Filament Copilot Screenshot 6" width="100%">
</p>

<p align="center">
  <img src="screenshot-7.png" alt="Filament Copilot Screenshot 7" width="100%">
</p>

<p align="center">
  <img src="screenshot-8.png" alt="Filament Copilot Screenshot 8" width="100%">
</p>

---

## Features

- **AI Chat Interface** — A beautiful, real-time chat modal built with Livewire, injected directly into your Filament panels.
- **Real-Time SSE Streaming** — Responses stream token-by-token via Server-Sent Events for an instant, interactive experience.
- **Context-Aware Agent** — The AI agent automatically discovers your copilot-enabled resources, pages, and widgets and builds context from them.
- **Tool Execution** — The agent can call tools you define to list, search, create, edit, delete, and restore records — or perform any custom action.
- **9 Tool Templates** — Generate tools instantly with the `make:copilot-tool` artisan command (list, view, search, create, edit, delete, force-delete, restore, custom).
- **Conversation History** — Full conversation persistence with a sidebar for browsing, loading, and deleting past conversations.
- **Agent Memory** — The AI remembers facts across conversations using a per-user key-value memory store (Remember & Recall tools).
- **Message Feedback** — Thumbs up/down on assistant replies, surfaced back to admins as a thumbs-down count and a "has negative rating" filter on the conversations table.
- **Audit Logging** — Comprehensive audit trail tracking messages, tool calls, record access, and navigation events.
- **Rate Limiting** — Per-user hourly/daily message and token rate limits with automatic blocking and unblocking.
- **Token Budget Tracking** — Daily and monthly token budget tracking with configurable warning thresholds.
- **Management Dashboard** — Optional admin dashboard with stats overview, token usage charts, top users table, and full Filament resources for conversations, audit logs, and rate limits.
- **Quick Actions** — Define canned prompt shortcuts that appear in the chat for one-click common queries.
- **8 Supported AI Providers** — OpenAI, Anthropic, Google Gemini, Groq, xAI, DeepSeek, Mistral, and Ollama (local models).
- **Keyboard Shortcut** — Open the copilot with `Ctrl+Shift+K` from anywhere in your panel.
- **Authorization Aware** — Respects Filament's authorization policies out of the box.
- **Fully Translatable** — All UI strings are translatable with a complete English language file (100+ keys).
- **Publishable Assets, Config, Views, Stubs** — Customize everything to fit your needs.
- **121 Tests** — Comprehensive test suite covering models, services, tools, Livewire components, views, discovery, enums, config, plugin, and streaming.

---

## Compatibility

| Dependency                   | Version                       |
| ---------------------------- | ----------------------------- |
| PHP                          | ^8.2                          |
| Laravel                      | 11.x / 12.x                   |
| Filament                     | ^5.0                          |
| Livewire                     | ^3.5 (ships with Filament v5) |
| Laravel AI SDK               | ^0.2.7                        |
| Spatie Laravel Package Tools | ^1.16                         |

> **Note:** This package is built for **Filament v5** and **Livewire 3.5+** (as bundled with Filament v5). It leverages the official **Laravel AI SDK** (`laravel/ai`) for all AI operations.

---

## Requirements

- PHP 8.2 or higher
- Laravel 11 or 12
- Filament v5
- A supported AI provider API key (or Ollama for local models)
- Node.js (only if you need to rebuild the CSS assets)

---

## Installation

### Step 1 — Install via Composer

```bash
composer require eslam-reda-div/filament-copilot
```

### Step 2 — Run the Install Command

The interactive installer will guide you through all setup steps:

```bash
php artisan filament-copilot:install
```

This command will:

1. **Publish the configuration** file to `config/filament-copilot.php`
2. **Publish CSS/JS assets** to `public/vendor/filament-copilot/`
3. **Publish and run database migrations** (7 tables)
4. **Publish the Laravel AI SDK config** (`config/ai.php`)
5. **Configure your AI provider** (OpenAI, Anthropic, Gemini, etc.)
6. **Select your AI model** from popular options or enter a custom one
7. **Set up your API key** in the `.env` file
8. **Display a summary** of all configured settings

### Step 3 — Manual Installation (Alternative)

If you prefer to set things up manually:

```bash
# Publish configuration
php artisan vendor:publish --tag=filament-copilot-config

# Publish assets
php artisan vendor:publish --tag=filament-copilot-assets

# Publish migrations
php artisan vendor:publish --tag=filament-copilot-migrations

# Run migrations
php artisan migrate

# Publish Laravel AI SDK config
php artisan vendor:publish --tag=ai-config
```

Then add the following to your `.env` file:

```env
COPILOT_PROVIDER=openai
COPILOT_MODEL=gpt-4o
OPENAI_API_KEY=your-api-key-here
```

---

## Configuration

The configuration file is published to `config/filament-copilot.php`. Here is a breakdown of every option:

### AI Provider & Model

```php
'provider' => env('COPILOT_PROVIDER', 'openai'),
'model'    => env('COPILOT_MODEL'),
```

Set via your `.env` file. See [Supported AI Providers](#supported-ai-providers) for the full list.

### Rate Limiting

```php
'rate_limits' => [
    'enabled'               => false,
    'max_messages_per_hour'  => 60,
    'max_messages_per_day'   => 500,
    'max_tokens_per_hour'    => 100000,
    'max_tokens_per_day'     => 1000000,
],
```

Per-user rate limiting with automatic blocking. Control messages and tokens per hour and per day.

### Token Budget

```php
'token_budget' => [
    'enabled'             => false,
    'warn_at_percentage'  => 80,
    'daily_budget'        => null,
    'monthly_budget'      => null,
],
```

Track your AI spending with daily/monthly token budgets and configurable warning thresholds.

### Audit Logging

```php
'audit' => [
    'enabled'            => true,
    'log_messages'       => true,
    'log_tool_calls'     => true,
    'log_record_access'  => true,
    'log_navigation'     => false,
],
```

Comprehensive audit trail. Enable or disable logging granularly per action type.

### Agent Memory

```php
'memory' => [
    'enabled'              => true,
    'max_memories_per_user' => 100,
],
```

The AI agent can remember facts across conversations. Memories are scoped per-user, per-panel, and per-tenant.

### Management Dashboard

```php
'management' => [
    'enabled' => false,
    'guard'   => null,
],
```

Enable the built-in management UI to view conversations, audit logs, rate limits, token usage charts, and top users — all within your Filament panel.

### Message Feedback

```php
'feedback' => [
    'enabled' => true,
],
```

Adds a thumbs up / thumbs down control under every assistant reply in the chat. One click rates the reply, clicking the lit thumb again clears it, and the rating is stored on `copilot_messages.rating` (`positive`, `negative`, or `null`).

Ratings are only accepted for assistant messages in the signed-in participant's own conversations, scoped by panel and tenant like every other conversation lookup in the package.

Admins get the feedback back in the management dashboard's **Conversations** resource:

- a **Thumbs Down** badge column, shown only for the conversations that actually collected one, and
- a **Has Negative Rating** filter for jumping straight to the conversations that went wrong.

Set `enabled` to `false` to hide the buttons and stop accepting ratings. Ratings already recorded are kept and still reported in the management UI.

### Quick Actions

```php
'quick_actions' => [
    // 'Summarize this page' => 'Summarize what this page is about.',
    // 'List all users'      => 'List all users in the system.',
],
```

Define one-click prompt shortcuts that appear in the chat interface.

### System Prompt

```php
'system_prompt' => null,
```

Override the default system prompt with your own. The agent uses a carefully crafted default prompt that includes context discovery, tool usage guidelines, and response formatting rules.

### Global Tools

```php
'global_tools' => [
    // \App\CopilotTools\MyGlobalTool::class,
],
```

Register tool classes that should be available on **every** page across the panel, regardless of which resource, page, or widget the user is on.

---

## Panel Registration

### Basic Registration

Register the plugin in your Filament panel provider:

```php
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentCopilotPlugin::make());
}
```

### Full Configuration via Plugin API

The plugin offers a fluent API for inline configuration that overrides the config file values:

```php
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentCopilotPlugin::make()
                ->provider('anthropic')
                ->model('claude-sonnet-4')
                ->systemPrompt('You are a helpful admin assistant.')
                ->globalTools([
                    \App\CopilotTools\SearchEverythingTool::class,
                ])
                ->quickActions([
                    'Show stats'   => 'Show me a summary of today\'s statistics.',
                    'Recent users' => 'List the 10 most recently created users.',
                ])
                ->managementEnabled()
                ->managementGuard('admin')
                ->rateLimitEnabled()
                ->tokenBudgetEnabled()
                ->dailyTokenBudget(50000)
                ->monthlyTokenBudget(1000000)
                ->memoryEnabled()
                ->maxMemoriesPerUser(200)
                ->respectAuthorization()
                ->authorizeUsing(fn ($user) => $user->is_admin)
        );
}
```

---

## Making Resources Copilot-Enabled

Implement the `CopilotResource` interface on any Filament resource to make it discoverable by the AI agent:

```php
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource;

class UserResource extends Resource implements CopilotResource
{
    // ... your existing resource code ...

    public static function copilotResourceDescription(): ?string
    {
        return 'Manages user accounts including names, emails, roles, and permissions.';
    }

    public static function copilotTools(): array
    {
        return [
            new \App\Filament\Resources\UserResource\CopilotTools\ListUsersTool(),
            new \App\Filament\Resources\UserResource\CopilotTools\SearchUsersTool(),
            new \App\Filament\Resources\UserResource\CopilotTools\CreateUserTool(),
            new \App\Filament\Resources\UserResource\CopilotTools\ViewUserTool(),
            new \App\Filament\Resources\UserResource\CopilotTools\EditUserTool(),
            new \App\Filament\Resources\UserResource\CopilotTools\DeleteUserTool(),
        ];
    }
}
```

---

## Making Pages Copilot-Enabled

Implement the `CopilotPage` interface on any Filament page:

```php
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage;

class Dashboard extends Page implements CopilotPage
{
    // ... your existing page code ...

    public static function copilotPageDescription(): ?string
    {
        return 'The main dashboard showing key metrics and recent activity.';
    }

    public static function copilotTools(): array
    {
        return [
            new \App\Filament\Pages\CopilotTools\Dashboard\DashboardStatsTool(),
        ];
    }
}
```

---

## Making Widgets Copilot-Enabled

Implement the `CopilotWidget` interface on any Filament widget:

```php
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotWidget;

class RevenueChart extends Widget implements CopilotWidget
{
    // ... your existing widget code ...

    public static function copilotWidgetDescription(): ?string
    {
        return 'Displays revenue data over the past 30 days as a line chart.';
    }

    public static function copilotTools(): array
    {
        return [
            new \App\Filament\Widgets\CopilotTools\RevenueChart\GetRevenueDataTool(),
        ];
    }
}
```

---

## Creating Copilot Tools

### Using the Generator Command

The `make:copilot-tool` artisan command provides an interactive 5-step wizard:

```bash
php artisan make:copilot-tool
```

**Step 1** — Select the Filament panel  
**Step 2** — Choose the tool type (Resource / Page / Widget)  
**Step 3** — Select the target (lists only copilot-enabled resources/pages/widgets from the chosen panel)  
**Step 4** — Pick a template (9 options for resources, custom for pages/widgets)  
**Step 5** — Enter the tool class name (with a smart default using proper English pluralization)

The command generates the tool file in the correct folder and namespace, with a summary table showing all settings.

### Tool Templates

For **resource tools**, 9 templates are available:

| Template       | Description                    | Default Name Example  |
| -------------- | ------------------------------ | --------------------- |
| `list`         | List records with pagination   | `ListUsersTool`       |
| `view`         | View a single record by ID     | `ViewUserTool`        |
| `search`       | Search records by keyword      | `SearchUsersTool`     |
| `create`       | Create a new record            | `CreateUserTool`      |
| `edit`         | Edit/update an existing record | `EditUserTool`        |
| `delete`       | Delete a record                | `DeleteUserTool`      |
| `force-delete` | Permanently delete a record    | `ForceDeleteUserTool` |
| `restore`      | Restore a soft-deleted record  | `RestoreUserTool`     |
| `custom`       | Blank template                 | `UserTool`            |

For **page and widget tools**, only the `custom` template is available.

### Writing a Custom Tool

Every tool extends `BaseTool` and implements three methods:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\CopilotTools;

use App\Filament\Resources\UserResource;
use EslamRedaDiv\FilamentCopilot\Tools\BaseTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchUsersTool extends BaseTool
{
    /**
     * A description of what this tool does — shown to the AI agent.
     */
    public function description(): Stringable|string
    {
        return 'Search users by name or email.';
    }

    /**
     * The JSON Schema defining the tool's parameters.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search keyword (name or email)')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of results to return')
                ->default(10),
        ];
    }

    /**
     * Execute the tool and return a string result to the AI agent.
     */
    public function handle(Request $request): Stringable|string
    {
        $model = UserResource::getModel();
        $query = (string) $request['query'];
        $limit = (int) ($request['limit'] ?? 10);

        $results = $model::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit($limit)
            ->get(['id', 'name', 'email']);

        if ($results->isEmpty()) {
            return "No users found matching '{$query}'.";
        }

        return $results->map(fn ($user) =>
            "#{$user->id} — {$user->name} ({$user->email})"
        )->implode("\n");
    }
}
```

### Folder Structure

Tools are generated in a specific folder structure depending on the type:

**Resource tools:**

```
app/Filament/Resources/
└── UserResource/
    ├── UserResource.php
    └── CopilotTools/
        ├── ListUsersTool.php
        ├── SearchUsersTool.php
        ├── CreateUserTool.php
        └── ...
```

**Page tools:**

```
app/Filament/Pages/
├── Dashboard.php
└── CopilotTools/
    └── Dashboard/
        └── DashboardStatsTool.php
```

**Widget tools:**

```
app/Filament/Widgets/
├── RevenueChart.php
└── CopilotTools/
    └── RevenueChart/
        └── GetRevenueDataTool.php
```

### Registering Tools

After generating a tool, register it in the `copilotTools()` method of your resource, page, or widget:

```php
public static function copilotTools(): array
{
    return [
        new \App\Filament\Resources\UserResource\CopilotTools\ListUsersTool(),
        new \App\Filament\Resources\UserResource\CopilotTools\SearchUsersTool(),
    ];
}
```

---

## Built-in Tools

The following tools are automatically available to the AI agent without any configuration:

| Tool                | Description                                                        |
| ------------------- | ------------------------------------------------------------------ |
| `ListResourcesTool` | Lists all copilot-enabled resources in the current panel           |
| `ListPagesTool`     | Lists all copilot-enabled pages in the current panel               |
| `ListWidgetsTool`   | Lists all copilot-enabled widgets in the current panel             |
| `GetToolsTool`      | Discovers available tools for a specific resource, page, or widget |
| `RunToolTool`       | Executes a discovered tool with provided arguments                 |
| `RememberTool`      | Stores a key-value memory for the current user                     |
| `RecallTool`        | Retrieves a stored memory (or lists all memories)                  |

The agent uses these tools to navigate your panel structure and execute operations dynamically.

---

## User Model Setup

To enable conversation history on your User model, add the `HasCopilotChat` trait:

```php
use EslamRedaDiv\FilamentCopilot\Concerns\HasCopilotChat;

class User extends Authenticatable
{
    use HasCopilotChat;

    // ...
}
```

This adds a `copilotConversations()` polymorphic relationship to your user model.

---

## How It Works

### Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Filament Panel                     │
│                                                      │
│  ┌──────────┐  ┌───────────────┐  ┌──────────────┐ │
│  │  Copilot  │  │ Conversation  │  │   Copilot    │ │
│  │  Button   │──│   Sidebar     │  │   Chat       │ │
│  └──────────┘  └───────────────┘  └──────┬───────┘ │
│                                           │         │
│                                    SSE Stream       │
│                                           │         │
├───────────────────────────────────────────┼─────────┤
│                                           ▼         │
│  ┌────────────────────────────────────────────────┐ │
│  │              StreamController                   │ │
│  │  Auth → Rate Limit → Build Agent → Stream      │ │
│  └──────────────────────┬─────────────────────────┘ │
│                          │                           │
│  ┌───────────────────────▼───────────────────────┐  │
│  │              CopilotAgent                      │  │
│  │  Temperature: 0.3 | MaxTokens: 4096           │  │
│  │  Middleware: Audit + RateLimit                 │  │
│  └───────────────────────┬───────────────────────┘  │
│                          │                           │
│  ┌───────────┐  ┌───────▼────────┐  ┌───────────┐  │
│  │  Context   │  │  Tool Registry │  │ Conversa- │  │
│  │  Builder   │  │  (built-in +   │  │   tion    │  │
│  │            │  │   global +     │  │  Manager  │  │
│  │ • Resources│  │   per-target)  │  │           │  │
│  │ • Pages    │  └────────────────┘  └───────────┘  │
│  │ • Widgets  │                                      │
│  │ • Memory   │                                      │
│  └───────────┘                                       │
│                                                      │
│  ┌──────────────────────────────────────────────┐   │
│  │            Laravel AI SDK                     │   │
│  │   OpenAI | Anthropic | Gemini | Groq | ...   │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### Streaming

1. User sends a message from the chat modal.
2. A `POST` request is made to `/copilot/stream`.
3. The `StreamController` validates auth, checks rate limits, and creates/loads the conversation.
4. The `CopilotAgent` is built with tools, messages, and middleware.
5. The response streams back via **Server-Sent Events** (SSE) — text deltas, tool calls, and tool results are sent as they happen.
6. The Livewire component renders the streamed response in real-time.

### Agent & Context Building

The `ContextBuilder` assembles the system prompt from multiple sources:

1. **Base system prompt** — Crafted guidelines for the AI agent, including response formatting rules and tool usage instructions.
2. **Resource discovery** — All copilot-enabled resources, their descriptions, and available tools.
3. **Page discovery** — All copilot-enabled pages with descriptions and tools.
4. **Widget discovery** — All copilot-enabled widgets with descriptions and tools.
5. **Agent memories** — Previously stored key-value memories for the current user.
6. **Custom prompt override** — Your own system prompt (if configured).

### Tool Execution Flow

1. The AI agent decides to call a tool based on the conversation context.
2. The tool call event is streamed to the frontend.
3. `RunToolTool` resolves the target tool class and executes it with the provided arguments.
4. The tool result is streamed back to the AI agent, which incorporates it into its response.
5. All tool calls are logged in the `copilot_tool_calls` table with full status tracking.

---

## Events

Filament Copilot dispatches the following events that you can listen to:

| Event                         | When                                           |
| ----------------------------- | ---------------------------------------------- |
| `CopilotConversationCreated`  | A new conversation is created                  |
| `CopilotMessageSent`          | A user sends a message                         |
| `CopilotResponseReceived`     | The AI agent responds                          |
| `CopilotToolExecuted`         | A tool is executed                             |
| `CopilotToolApprovalRequired` | A tool requires user approval before execution |
| `CopilotRateLimitExceeded`    | A user exceeds their rate limit                |

```php
// In your EventServiceProvider or event discovery
use EslamRedaDiv\FilamentCopilot\Events\CopilotToolExecuted;

class LogToolExecution
{
    public function handle(CopilotToolExecuted $event): void
    {
        logger()->info('Tool executed', [
            'tool'    => $event->toolCall->tool_class,
            'user_id' => $event->toolCall->conversation->participant_id,
        ]);
    }
}
```

---

## Models & Database

The package creates 7 database tables:

| Table                    | Model                 | Description                                                  |
| ------------------------ | --------------------- | ------------------------------------------------------------ |
| `copilot_conversations`  | `CopilotConversation` | Conversation storage with polymorphic participant and tenant |
| `copilot_messages`       | `CopilotMessage`      | Message history with role enum, token tracking, and feedback rating |
| `copilot_tool_calls`     | `CopilotToolCall`     | Tool call tracking with approval status workflow             |
| `copilot_audit_logs`     | `CopilotAuditLog`     | Detailed audit trail with 25 action types                    |
| `copilot_rate_limits`    | `CopilotRateLimit`    | Per-user rate limit configuration and blocking               |
| `copilot_token_usages`   | `CopilotTokenUsage`   | Daily token usage tracking per model and provider            |
| `copilot_agent_memories` | `CopilotAgentMemory`  | Per-user key-value memory store                              |

All models use ULIDs as primary keys and support polymorphic relationships for participant and tenant.

### Enums

| Enum             | Values                                                                                   |
| ---------------- | ---------------------------------------------------------------------------------------- |
| `MessageRole`    | `User`, `Assistant`, `System`, `Tool`                                                    |
| `MessageRating`  | `Positive`, `Negative`                                                                   |
| `ToolCallStatus` | `Pending`, `Approved`, `Rejected`, `Executed`, `Failed`                                  |
| `AuditAction`    | 25 actions including `MessageSent`, `ToolCalled`, `RecordCreated`, `RecordDeleted`, etc. |

---

## Management Dashboard

Enable the management dashboard to monitor copilot usage from within your Filament panel:

```php
FilamentCopilotPlugin::make()
    ->managementEnabled()
    ->managementGuard('admin')  // optional: restrict to specific guard
```

This adds:

- **Dashboard Page** — With 3 widgets:
  - **Stats Overview** — 4 cards showing total conversations, messages, tool calls, and active users
  - **Token Usage Chart** — 30-day line chart of daily token consumption
  - **Top Users Table** — Sortable table of the most active copilot users
- **Conversations Resource** — Browse, view, and manage all copilot conversations, including the thumbs-down count and the "has negative rating" filter from [Message Feedback](#message-feedback)
- **Audit Logs Resource** — Search and filter the complete audit trail
- **Rate Limits Resource** — Manage per-user rate limits and blocking

---

## Customization

### Custom System Prompt

Override the default system prompt via config or the plugin API:

```php
// config/filament-copilot.php
'system_prompt' => 'You are a helpful assistant for our e-commerce admin panel. Focus on order management and customer support.',
```

Or via the plugin:

```php
FilamentCopilotPlugin::make()
    ->systemPrompt('You are a helpful assistant for our e-commerce admin panel.')
```

### Authorization

Control who can access the copilot:

```php
FilamentCopilotPlugin::make()
    ->authorizeUsing(fn ($user) => $user->hasRole('admin'))
```

The package also respects Filament's built-in authorization policies when `respect_authorization` is enabled (default: `true`).

### Translations

Publish the translation files to customize all UI strings:

```bash
php artisan vendor:publish --tag=filament-copilot-translations
```

The package ships with a complete English translation file containing 100+ keys.

### Views

Publish the Blade views to customize the UI:

```bash
php artisan vendor:publish --tag=filament-copilot-views
```

### Publishing Stubs

Publish the tool generator stubs to customize the generated tool templates:

```bash
php artisan vendor:publish --tag=filament-copilot-stubs
```

Stubs are published to `stubs/filament-copilot/` in your project root.

---

## Testing

The package includes 121 tests with 254 assertions covering:

- Models & relationships
- Services (ConversationManager, ToolRegistry, RateLimitService, ExportService)
- Built-in tools
- Livewire components
- Blade views
- Discovery inspectors
- Enums
- Configuration
- Plugin registration
- Streaming

Run the tests:

```bash
cd packages/filament-copilot
php vendor/bin/pest
```

---

## Supported AI Providers

| Provider          | Env Key             | Example Models                                           |
| ----------------- | ------------------- | -------------------------------------------------------- |
| **OpenAI**        | `OPENAI_API_KEY`    | `gpt-4o`, `gpt-4o-mini`, `o3`, `o4-mini`                 |
| **Anthropic**     | `ANTHROPIC_API_KEY` | `claude-sonnet-4`, `claude-opus-4`, `claude-haiku-4`     |
| **Google Gemini** | `GEMINI_API_KEY`    | `gemini-2.0-flash`, `gemini-2.5-pro`, `gemini-2.5-flash` |
| **Groq**          | `GROQ_API_KEY`      | `llama-3.3-70b-versatile`, `mixtral-8x7b`                |
| **xAI**           | `XAI_API_KEY`       | `grok-3`, `grok-3-mini`                                  |
| **DeepSeek**      | `DEEPSEEK_API_KEY`  | `deepseek-chat`, `deepseek-reasoner`                     |
| **Mistral**       | `MISTRAL_API_KEY`   | `mistral-large-latest`, `codestral-latest`               |
| **Ollama**        | _(none — local)_    | `llama3`, `mistral`, `codellama`, `phi3`                 |

Configure via `.env`:

```env
COPILOT_PROVIDER=openai
COPILOT_MODEL=gpt-4o
OPENAI_API_KEY=sk-...
```

---

## Contributing

We warmly welcome contributions from the **Laravel** and **FilamentPHP** open-source community! Whether it's a bug fix, a new feature, improved documentation, or just a typo fix — every contribution matters and is greatly appreciated.

### How to Contribute

1. **Fork** the repository
2. **Create a branch** for your feature or fix:
   ```bash
   git checkout -b feature/my-awesome-feature
   ```
3. **Make your changes** and write tests if applicable
4. **Run the test suite** to make sure nothing is broken:
   ```bash
   php vendor/bin/pest
   ```
5. **Commit** your changes with a clear message:
   ```bash
   git commit -m "Add: my awesome feature"
   ```
6. **Push** to your fork and **open a Pull Request**

### Reporting Issues

Found a bug? Have a question? Want to request a feature?

- **Open an issue** on the [GitHub Issues](https://github.com/eslam-reda-div/filament-copilot/issues) page
- Describe the problem clearly, including steps to reproduce if it's a bug
- Include your PHP, Laravel, and Filament version numbers
- Attach screenshots or error logs if applicable

We review all issues and pull requests and try to respond as quickly as possible. Thank you to everyone who takes the time to help improve this package — you're what makes the open-source ecosystem great! ❤️

### Development Setup

```bash
# Clone the repository
git clone https://github.com/eslam-reda-div/filament-copilot.git
cd filament-copilot

# Install dependencies
composer install

# Run tests
php vendor/bin/pest

# Build assets (if modifying CSS)
npm install
npm run build
```

---

## Security

If you discover a security vulnerability, please report it responsibly. Send an email to the maintainer instead of opening a public issue. All security vulnerabilities will be promptly addressed.

---

## Credits

- [Eslam Reda](https://github.com/eslam-reda-div)
- [All Contributors](https://github.com/eslam-reda-div/filament-copilot/graphs/contributors)

Built with:

- [FilamentPHP](https://filamentphp.com) — The elegant TALL stack admin panel framework
- [Laravel AI SDK](https://github.com/laravel/ai) — The official Laravel AI package
- [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools) — Package scaffolding utilities

---

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
