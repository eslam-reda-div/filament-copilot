<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Http\Controllers;

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Events\CopilotMessageSent;
use EslamRedaDiv\FilamentCopilot\Events\CopilotResponseReceived;
use EslamRedaDiv\FilamentCopilot\Events\CopilotToolApprovalRequired;
use EslamRedaDiv\FilamentCopilot\Events\CopilotToolExecuted;
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotToolCall;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController
{
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:10000', 'required_without:decisions', 'prohibits:decisions'],
            'decisions' => ['nullable', 'array', 'required_without:message', 'prohibits:message'],
            'decisions.*.action' => ['required_with:decisions', 'in:approve,reject'],
            'decisions.*.result' => ['nullable', 'string'],
            'conversation_id' => ['nullable', 'string'],
            'panel_id' => ['required', 'string'],
        ]);

        $panelId = $request->input('panel_id');

        // Set up Filament panel context so the correct auth guard is used
        try {
            Filament::setCurrentPanel($panelId);
        } catch (\Throwable) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $user = Filament::auth()->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        /** @var FilamentCopilotPlugin $plugin */
        $plugin = FilamentCopilotPlugin::get();

        if (! $plugin->isAuthorized($user)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $tenant = Filament::getTenant();
        $content = $request->input('message');
        $decisions = $request->input('decisions');
        $conversationId = $request->input('conversation_id');

        /** @var RateLimitService $rateLimitService */
        $rateLimitService = app(RateLimitService::class);

        if (! $decisions && config('filament-copilot.rate_limits.enabled') && ! $rateLimitService->canSendMessage($user, $panelId, $tenant)) {
            return $this->sseResponse(function () {
                $this->sendSseEvent('error', ['message' => __('filament-copilot::filament-copilot.rate_limit_exceeded')]);
                $this->sendSseEvent('done', []);
            });
        }

        /** @var ConversationManager $conversationManager */
        $conversationManager = app(ConversationManager::class);

        if ($conversationId) {
            $conversation = CopilotConversation::query()
                ->forPanel($panelId)
                ->forParticipant($user)
                ->forTenant($tenant)
                ->find($conversationId);

            if (! $conversation) {
                return $this->sseResponse(function () {
                    $this->sendSseEvent('error', ['message' => 'Conversation not found.']);
                    $this->sendSseEvent('done', []);
                });
            }
        } else {
            $conversation = $conversationManager->create($user, $panelId, $tenant);
        }

        $userMessage = null;

        if ($content !== null) {
            $userMessage = $conversationManager->addUserMessage($conversation, $content);
            event(new CopilotMessageSent($conversation, $content, $panelId));
        } elseif (! ($conversation->metadata['ai_conversation_id'] ?? null)) {
            return $this->sseResponse(function () {
                $this->sendSseEvent('error', ['message' => 'This conversation cannot be resumed.']);
                $this->sendSseEvent('done', []);
            });
        }

        return $this->sseResponse(function () use ($conversation, $conversationManager, $user, $panelId, $tenant, $rateLimitService, $plugin, $userMessage, $content, $decisions) {
            $this->sendSseEvent('conversation', ['id' => $conversation->id]);

            try {
                /** @var ToolRegistry $toolRegistry */
                $toolRegistry = app(ToolRegistry::class);

                /** @var CopilotAgent $agent */
                $agent = app(CopilotAgent::class);

                $sdkConversationId = $conversation->metadata['ai_conversation_id'] ?? null;

                $agent->forPanel($panelId)
                    ->forTenant($tenant)
                    ->withTools($toolRegistry->buildTools($panelId, $user, $tenant, $conversation->id))
                    ->withSystemPrompt($plugin->getSystemPrompt());

                if ($sdkConversationId) {
                    $agent->continue($sdkConversationId, as: $user);
                } else {
                    $agent->forUser($user);
                }

                $provider = $plugin->getProvider();
                $model = $plugin->getModel();

                // Send start event
                $this->sendSseEvent('start', []);

                $prompt = $decisions
                    ? \Laravel\Ai\Approvals\Decisions::from(collect($decisions)->map(
                        fn (array $decision) => ($decision['action'] ?? null) === 'approve'
                            ? \Laravel\Ai\Approvals\Decision::approve()
                            : \Laravel\Ai\Approvals\Decision::reject($decision['result'] ?? null)
                    )->all())
                    : $content;

                $streamResponse = $agent->stream(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                );
                $sdkConversationId = null;
                $streamResponse->then(function ($response) use (&$sdkConversationId): void {
                    $sdkConversationId = $response->conversationId;
                });

                $responseText = '';
                $usage = null;
                $shouldLogToolCalls = config('filament-copilot.audit.enabled', true)
                    && config('filament-copilot.audit.log_tool_calls', true);

                /** @var array<string, CopilotToolCall> $toolCallsByProviderId */
                $toolCallsByProviderId = [];
                $pendingApprovals = [];
                $toolCallMessage = $userMessage ?: $conversation->messages()
                    ->where('role', \EslamRedaDiv\FilamentCopilot\Enums\MessageRole::User)
                    ->latest()
                    ->first();

                if ($decisions && $toolCallMessage) {
                    foreach ($decisions as $providerId => $decision) {
                        $toolCall = $toolCallMessage->toolCalls()->where('provider_id', $providerId)->first();

                        if ($toolCall) {
                            ($decision['action'] ?? null) === 'approve'
                                ? $toolCall->approve()
                                : $toolCall->reject();
                            $toolCallsByProviderId[$providerId] = $toolCall;
                        }
                    }
                }

                // Stream real-time chunks from the AI provider
                foreach ($streamResponse as $event) {
                    if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                        $responseText .= $event->delta;
                        $this->sendSseEvent('chunk', ['text' => $event->delta]);
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolCall) {
                        $this->sendSseEvent('tool_call', [
                            'tool_id' => $event->toolCall->id,
                            'tool_name' => $event->toolCall->name,
                            'arguments' => $event->toolCall->arguments,
                        ]);

                        if ($shouldLogToolCalls) {
                            $toolCallsByProviderId[$event->toolCall->id] = $toolCallMessage?->toolCalls()->create([
                                'provider_id' => $event->toolCall->id,
                                'tool_name' => $event->toolCall->name,
                                'tool_input' => $event->toolCall->arguments,
                                'status' => ToolCallStatus::Pending,
                            ]);
                        }
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolResult) {
                        $rawResult = is_string($event->toolResult->result) ? $event->toolResult->result : json_encode($event->toolResult->result);
                        $denied = (bool) ($event->denied ?? false);

                        $this->sendSseEvent('tool_result', [
                            'tool_id' => $event->toolResult->id ?? '',
                            'tool_name' => $event->toolResult->name ?? '',
                            'result' => $rawResult,
                            'success' => $event->successful && ! $denied,
                            'error' => $event->error,
                            'denied' => $denied,
                        ]);

                        if ($shouldLogToolCalls) {
                            $providerToolCallId = $event->toolResult->id;

                            $toolCall = $toolCallsByProviderId[$providerToolCallId] ?? null;

                            if (! $toolCall) {
                                $toolCall = $toolCallMessage?->toolCalls()->create([
                                    'provider_id' => $providerToolCallId,
                                    'tool_name' => $event->toolResult->name,
                                    'tool_input' => $event->toolResult->arguments,
                                    'status' => ToolCallStatus::Pending,
                                ]);

                                $toolCallsByProviderId[$providerToolCallId] = $toolCall;
                            }

                            // A rejected approval still yields a (denied) tool
                            // result; keep the Rejected status the decision
                            // recorded instead of reporting it as executed.
                            $toolCall->update([
                                'status' => match (true) {
                                    $denied || $toolCall->status === ToolCallStatus::Rejected => ToolCallStatus::Rejected,
                                    $event->successful => ToolCallStatus::Executed,
                                    default => ToolCallStatus::Failed,
                                },
                                'tool_output' => $rawResult,
                            ]);

                            event(new CopilotToolExecuted(
                                toolCall: $toolCall->fresh(),
                                toolName: $toolCall->tool_name,
                                result: $rawResult,
                            ));
                        }
                    } elseif (get_class($event) === 'Laravel\\Ai\\Streaming\\Events\\ToolApprovalRequest') {
                        foreach ($event->pendingApprovals as $approval) {
                            $pendingApprovals[] = $approval->toArray();

                            // $toolCallMessage (not $userMessage) so a resumed turn
                            // that pauses again is still logged against the user
                            // message the turn belongs to.
                            if ($shouldLogToolCalls && $toolCallMessage) {
                                $toolCall = $toolCallsByProviderId[$approval->id] ?? $toolCallMessage->toolCalls()
                                    ->where('tool_name', $approval->tool)
                                    ->where('status', ToolCallStatus::Pending)
                                    ->latest()
                                    ->first();

                                if (! $toolCall) {
                                    $toolCall = $toolCallMessage?->toolCalls()->create([
                                        'provider_id' => $approval->id,
                                        'tool_name' => $approval->tool,
                                        'tool_input' => $approval->arguments,
                                        'status' => ToolCallStatus::Pending,
                                        'requires_approval' => true,
                                    ]);
                                } else {
                                    $toolCall->update([
                                        'tool_input' => $approval->arguments,
                                        'requires_approval' => true,
                                    ]);
                                }

                                $toolCallsByProviderId[$approval->id] = $toolCall;
                                event(new CopilotToolApprovalRequired($toolCall->fresh()));
                            }
                        }

                        $this->sendSseEvent('tool_approval_request', ['approvals' => $pendingApprovals]);
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\StreamEnd) {
                        $usage = $event->usage;
                    }
                }

                // Fallback: get usage from the streamable response if not captured from events
                if ($usage === null) {
                    $usage = $streamResponse->usage;
                }

                $sdkConversationId ??= $streamResponse->conversationId;

                if ($sdkConversationId) {
                    $conversation->update([
                        'metadata' => array_merge($conversation->metadata ?? [], [
                            'ai_conversation_id' => $sdkConversationId,
                        ]),
                    ]);
                }

                // Store the complete message. A turn that paused for approval
                // keeps its pending approvals on the message, so reopening the
                // conversation can show the approval card again and resume it.
                $assistantMessage = $conversationManager->addAssistantMessage(
                    conversation: $conversation,
                    content: $responseText,
                    inputTokens: $usage->promptTokens ?? 0,
                    outputTokens: $usage->completionTokens ?? 0,
                    metadata: $pendingApprovals !== [] ? ['pending_approvals' => $pendingApprovals] : null,
                );

                // Record token usage
                if (config('filament-copilot.rate_limits.enabled')) {
                    $rateLimitService->recordTokenUsage(
                        user: $user,
                        panelId: $panelId,
                        inputTokens: $usage->promptTokens ?? 0,
                        outputTokens: $usage->completionTokens ?? 0,
                        tenant: $tenant,
                        conversationId: $conversation->id,
                        model: $model,
                        provider: $provider,
                    );
                }

                event(new CopilotResponseReceived(
                    $conversation,
                    $assistantMessage,
                    $usage->promptTokens ?? 0,
                    $usage->completionTokens ?? 0,
                ));

                $this->sendSseEvent('usage', [
                    'input_tokens' => $usage->promptTokens ?? 0,
                    'output_tokens' => $usage->completionTokens ?? 0,
                ]);

                // The persisted assistant message id lets the chat component
                // reference the reply it just streamed — for message feedback,
                // and for anything else that needs to address it later. Older
                // published copies of the chat view simply ignore the payload.
                $this->sendSseEvent('done', [
                    'message_id' => $assistantMessage->id,
                    'approvals' => $pendingApprovals,
                ]);
            } catch (\Throwable $e) {
                // Raw exception messages can carry SQL text and bindings
                // (QueryException), file paths or other internal state — none
                // of which belongs in an end user's chat bubble. Log the full
                // exception server-side under a short correlation id, and only
                // echo the raw message into the stream when the app runs with
                // debug enabled; otherwise the user gets a generic line plus
                // the id, which support can match to the log entry instantly.
                $ref = substr((string) Str::ulid(), -6);
                report(new \RuntimeException("[copilot:{$ref}] ".$e->getMessage(), previous: $e));

                $this->sendSseEvent('error', [
                    'message' => app()->hasDebugModeEnabled()
                        ? $e->getMessage()
                        : __('filament-copilot::filament-copilot.stream_error', ['ref' => $ref]),
                ]);
                $this->sendSseEvent('done', []);
            }
        });
    }

    protected function sseResponse(callable $callback): StreamedResponse
    {
        return new StreamedResponse(function () use ($callback) {
            // Disable output buffering for real-time streaming
            if (ob_get_level()) {
                ob_end_clean();
            }

            $callback();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
