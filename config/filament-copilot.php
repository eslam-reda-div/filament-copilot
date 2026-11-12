<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'provider' => env('FILAMENT_COPILOT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    */

    'model' => env('FILAMENT_COPILOT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Agent Behavior
    |--------------------------------------------------------------------------
    */

    'agent' => [
        // Seconds the provider may take for one request. Reasoning models
        // can think for a long time before they answer.
        'timeout' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Clarifying Questions
    |--------------------------------------------------------------------------
    | Gives the model an `ask_user` tool. When it needs information or a
    | choice from the user, the turn pauses and the chat shows the question
    | (with one-click answer options); the answer resumes the same turn.
    | Requires Laravel AI's conversation tables, like tool approvals.
    */

    'ask_user' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat History
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'title_auto_generate' => true,

        /*
        |----------------------------------------------------------------
        | Markdown HTML Handling
        |----------------------------------------------------------------
        | How raw HTML inside assistant/model-generated message content is
        | treated when it is rendered as markdown in the chat bubble.
        |
        | Assistant message content is untrusted: it comes from the model,
        | and the model's output can be steered by prompt injection (e.g.
        | text read from a tool result, a document, or a record). Without
        | escaping, HTML/JS embedded in that output (a <script> tag, an
        | onerror handler, ...) would execute in the panel of whoever has
        | the chat open.
        |
        | Valid values (passed straight through to league/commonmark's
        | html_input option):
        | - 'escape' (default): HTML in the model's output is rendered as
        |   visible text instead of being parsed as markup. This is the
        |   safe default.
        | - 'strip': HTML in the model's output is silently removed.
        | - 'allow': HTML in the model's output is rendered as-is. This
        |   restores the pre-hardening behavior and is only safe for
        |   consumers who intentionally have their model emit trusted HTML.
        */

        'html_input' => 'escape',

        /*
        |----------------------------------------------------------------
        | Reasoning
        |----------------------------------------------------------------
        | Reasoning models think before they answer. While they do, the
        | chat shows "Thinking…" and lets the user expand the streamed
        | reasoning. Set to false to show only the indicator, without the
        | reasoning text.
        */

        'stream_reasoning' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'enabled' => false,
        'max_messages_per_hour' => 60,
        'max_messages_per_day' => 500,
        'max_tokens_per_hour' => 100000,
        'max_tokens_per_day' => 1000000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Budget
    |--------------------------------------------------------------------------
    */

    'token_budget' => [
        'enabled' => false,
        'warn_at_percentage' => 80,
        'daily_budget' => null,
        'monthly_budget' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'enabled' => true,
        'log_messages' => true,
        'log_tool_calls' => true,
        'log_record_access' => true,
        'log_navigation' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Memory
    |--------------------------------------------------------------------------
    */

    'memory' => [
        'enabled' => true,
        'max_memories_per_user' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Integration
    |--------------------------------------------------------------------------
    */

    'respect_authorization' => true,

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Management UI
    |--------------------------------------------------------------------------
    */

    'management' => [
        'enabled' => false,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Feedback
    |--------------------------------------------------------------------------
    | Thumbs up / down on assistant replies. When disabled the buttons are
    | hidden and ratings can no longer be submitted; ratings already stored on
    | copilot_messages.rating are kept and still reported in the management UI.
    */

    'feedback' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Actions / Canned Prompts
    |--------------------------------------------------------------------------
    */

    'quick_actions' => [],

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    */

    'system_prompt' => null,

    /*
    |--------------------------------------------------------------------------
    | Global Tools
    |--------------------------------------------------------------------------
    | Tool classes available on every page across all resources.
    | Each entry should be a class name that extends BaseTool.
    */

    'global_tools' => [],

];
