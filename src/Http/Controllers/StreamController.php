<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Http\Controllers;

use EslamRedaDiv\FilamentCopilot\Agent\CopilotAgent;
use EslamRedaDiv\FilamentCopilot\Enums\ToolCallStatus;
use EslamRedaDiv\FilamentCopilot\Events\CopilotMessageSent;
use EslamRedaDiv\FilamentCopilot\Events\CopilotResponseReceived;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController
{
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
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
        $conversationId = $request->input('conversation_id');

        /** @var RateLimitService $rateLimitService */
        $rateLimitService = app(RateLimitService::class);

        if (config('filament-copilot.rate_limits.enabled') && ! $rateLimitService->canSendMessage($user, $panelId, $tenant)) {
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

        $userMessage = $conversationManager->addUserMessage($conversation, $content);
        event(new CopilotMessageSent($conversation, $content, $panelId));

        return $this->sseResponse(function () use ($conversation, $conversationManager, $user, $panelId, $tenant, $rateLimitService, $plugin, $userMessage) {
            $this->sendSseEvent('conversation', ['id' => $conversation->id]);

            try {
                /** @var ToolRegistry $toolRegistry */
                $toolRegistry = app(ToolRegistry::class);

                /** @var CopilotAgent $agent */
                $agent = app(CopilotAgent::class);

                $messages = $conversationManager->getMessagesForAgent($conversation);

                // `addUserMessage()` above has already persisted the current user
                // message, so `getMessagesForAgent()` returns it as the trailing
                // row. Extract that content for `prompt:` and drop the row from
                // the history we pass to `withMessages()`. Otherwise laravel/ai's
                // `Promptable::stream()` wraps `prompt:` as a NEW user message on
                // top of the already-present row, duplicating the user's latest
                // message in every outgoing request body.
                $lastUserMessage = '';
                if (! empty($messages) && end($messages)['role'] === 'user') {
                    $lastUserMessage = array_pop($messages)['content'];
                }

                $agent->forPanel($panelId)
                    ->forUser($user)
                    ->forTenant($tenant)
                    ->withTools($toolRegistry->buildTools($panelId, $user, $tenant, $conversation->id))
                    ->withMessages($messages)
                    ->withSystemPrompt($plugin->getSystemPrompt());

                $provider = $plugin->getProvider();
                $model = $plugin->getModel();

                // Send start event
                $this->sendSseEvent('start', []);

                $streamResponse = $agent->stream(
                    prompt: $lastUserMessage,
                    provider: $provider,
                    model: $model,
                );

                $responseText = '';
                $usage = null;
                $shouldLogToolCalls = config('filament-copilot.audit.enabled', true)
                    && config('filament-copilot.audit.log_tool_calls', true);

                /** @var array<string, CopilotToolCall> $toolCallsByProviderId */
                $toolCallsByProviderId = [];

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
                            $toolCallsByProviderId[$event->toolCall->id] = $userMessage->toolCalls()->create([
                                'tool_name' => $event->toolCall->name,
                                'tool_input' => $event->toolCall->arguments,
                                'status' => ToolCallStatus::Pending,
                            ]);
                        }
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\ToolResult) {
                        $rawResult = is_string($event->toolResult->result) ? $event->toolResult->result : json_encode($event->toolResult->result);

                        $this->sendSseEvent('tool_result', [
                            'tool_id' => $event->toolResult->id ?? '',
                            'tool_name' => $event->toolResult->name ?? '',
                            'result' => $rawResult,
                            'success' => $event->successful,
                            'error' => $event->error,
                        ]);

                        if ($shouldLogToolCalls) {
                            $providerToolCallId = $event->toolResult->id;

                            $toolCall = $toolCallsByProviderId[$providerToolCallId] ?? null;

                            if (! $toolCall) {
                                $toolCall = $userMessage->toolCalls()->create([
                                    'tool_name' => $event->toolResult->name,
                                    'tool_input' => $event->toolResult->arguments,
                                    'status' => ToolCallStatus::Pending,
                                ]);

                                $toolCallsByProviderId[$providerToolCallId] = $toolCall;
                            }

                            $toolCall->update([
                                'status' => $event->successful ? ToolCallStatus::Executed : ToolCallStatus::Failed,
                                'tool_output' => $rawResult,
                            ]);

                            event(new CopilotToolExecuted(
                                toolCall: $toolCall->fresh(),
                                toolName: $toolCall->tool_name,
                                result: $rawResult,
                            ));
                        }
                    } elseif ($event instanceof \Laravel\Ai\Streaming\Events\StreamEnd) {
                        $usage = $event->usage;
                    }
                }

                // Fallback: get usage from the streamable response if not captured from events
                if ($usage === null) {
                    $usage = $streamResponse->usage;
                }

                // Store the complete message
                $assistantMessage = $conversationManager->addAssistantMessage(
                    conversation: $conversation,
                    content: $responseText,
                    inputTokens: $usage->promptTokens ?? 0,
                    outputTokens: $usage->completionTokens ?? 0,
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
                $this->sendSseEvent('done', ['message_id' => $assistantMessage->id]);
            } catch (\Throwable $e) {
                $this->sendSseEvent('error', ['message' => $e->getMessage()]);
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
