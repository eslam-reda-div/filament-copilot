<div x-data="{
    open: @entangle('isOpen'),
    sidebarOpen: false,
    streamingEnabled: @entangle('streamingEnabled'),
    isStreaming: false,
    streamedContent: '',
    pendingComplete: false,
    init() {
        this.$watch('open', value => {
            if (value) {
                this.$nextTick(() => this.scrollToBottom());
            } else {
                this.sidebarOpen = false;
            }
        });

        Livewire.hook('morph.updated', ({ el }) => {
            if (el.id === 'copilot-messages') {
                if (this.pendingComplete) {
                    this.pendingComplete = false;
                    this.streamedContent = '';
                    this.isStreaming = false;
                }
                this.$nextTick(() => this.scrollToBottom());
            }
        });

        Livewire.on('copilot-send-stream', (data) => {
            this.startStreaming(data[0] || data);
        });
    },
    scrollToBottom() {
        const container = this.$refs.messages;
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    },
    handleKeydown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!this.isStreaming && !$wire.isLoading) {
                $wire.sendMessage();
            }
        }
    },
    autoResize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    },
    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        if (this.sidebarOpen) {
            $wire.dispatch('copilot-refresh-sidebar');
        }
    },
    async startStreaming(params) {
        this.isStreaming = true;
        this.streamedContent = '';
        this.pendingComplete = false;

        try {
            const response = await fetch(params.streamUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': params.csrfToken,
                },
                body: JSON.stringify({
                    message: params.message,
                    conversation_id: params.conversationId,
                    panel_id: params.panelId,
                }),
            });

            if (!response.ok) {
                throw new Error('Stream request failed: ' + response.status);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let newConversationId = null;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                let currentEvent = null;

                for (const line of lines) {
                    if (line.startsWith('event: ')) {
                        currentEvent = line.substring(7).trim();
                    } else if (line.startsWith('data: ') && currentEvent) {
                        try {
                            const data = JSON.parse(line.substring(6));

                            switch (currentEvent) {
                                case 'conversation':
                                    newConversationId = data.id;
                                    break;
                                case 'chunk':
                                    this.streamedContent += data.text;
                                    this.$nextTick(() => this.scrollToBottom());
                                    break;
                                case 'plan_status':
                                    $wire.dispatch('copilot-plan-status', {
                                        id: data.id,
                                        status: data.status,
                                        currentStep: data.current_step,
                                        totalSteps: data.total_steps,
                                        steps: data.steps,
                                    });
                                    break;
                                case 'error':
                                    $wire.dispatch('copilot-stream-error', { error: data.message });
                                    this.isStreaming = false;
                                    this.streamedContent = '';
                                    break;
                                case 'done':
                                    break;
                            }
                        } catch (e) {
                            // Skip malformed JSON
                        }

                        currentEvent = null;
                    }
                }
            }

            // Keep streamedContent visible until Livewire re-renders with the final message
            if (this.streamedContent) {
                this.pendingComplete = true;
                $wire.dispatch('copilot-stream-complete', {
                    content: this.streamedContent,
                    newConversationId: newConversationId,
                });
            } else {
                this.isStreaming = false;
            }
        } catch (error) {
            $wire.dispatch('copilot-stream-error', { error: error.message });
            this.isStreaming = false;
            this.streamedContent = '';
        }
    }
}" x-show="open" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4" x-cloak @copilot-open.window="open = true"
    @copilot-close-sidebar.window="sidebarOpen = false" @copilot-load-conversation.window="sidebarOpen = false"
    class="fixed bottom-20 right-6 z-50 flex flex-col w-105 h-150 max-h-[80vh] bg-white dark:bg-gray-900 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 bg-primary-600 dark:bg-primary-700 text-white shrink-0">
        <div class="flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-sparkles" class="w-5 h-5" />
            <span class="font-semibold text-sm">{{ __('filament-copilot::filament-copilot.title') }}</span>
        </div>
        <div class="flex items-center gap-1">
            @if (config('filament-copilot.export.enabled', true))
                <button x-show="$wire.conversationId" wire:click="exportConversation" type="button"
                    class="p-1.5 rounded-lg hover:bg-white/20 transition"
                    title="{{ __('filament-copilot::filament-copilot.export') }}">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-4 h-4" />
                </button>
            @endif
            <button @click="toggleSidebar()" type="button" class="p-1.5 rounded-lg transition"
                :class="sidebarOpen ? 'bg-white/30' : 'hover:bg-white/20'"
                title="{{ __('filament-copilot::filament-copilot.history') }}">
                <x-filament::icon icon="heroicon-o-clock" class="w-4 h-4" />
            </button>
            <button wire:click="newConversation" type="button" class="p-1.5 rounded-lg hover:bg-white/20 transition"
                title="{{ __('filament-copilot::filament-copilot.new_conversation') }}">
                <x-filament::icon icon="heroicon-o-plus" class="w-4 h-4" />
            </button>
            <button @click="open = false" type="button" class="p-1.5 rounded-lg hover:bg-white/20 transition">
                <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
            </button>
        </div>
    </div>

    {{-- Content area with sidebar overlay --}}
    <div class="flex-1 flex relative overflow-hidden">

        {{-- Conversation Sidebar (Alpine-controlled overlay) --}}
        <div x-show="sidebarOpen" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 -translate-x-4" x-cloak
            class="absolute inset-0 z-10 bg-white dark:bg-gray-900 flex flex-col">
            @livewire('filament-copilot-sidebar', ['activeConversationId' => $conversationId])
        </div>

        {{-- Messages --}}
        <div id="copilot-messages" x-ref="messages" class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
            @if (empty($messages))
                <div
                    class="flex flex-col items-center justify-center h-full text-center text-gray-400 dark:text-gray-500 gap-3">
                    <x-filament::icon icon="heroicon-o-sparkles" class="w-10 h-10" />
                    <p class="text-sm">{{ __('filament-copilot::filament-copilot.welcome_message') }}</p>

                    {{-- Quick Actions --}}
                    @if (!empty(($quickActions = config('filament-copilot.quick_actions', []))))
                        <div class="flex flex-wrap justify-center gap-2 mt-2">
                            @foreach ($quickActions as $action)
                                <button
                                    wire:click="$dispatch('copilot-quick-action', { prompt: '{{ addslashes($action['prompt'] ?? $action) }}' })"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-full border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    @if (isset($action['icon']))
                                        <x-filament::icon :icon="$action['icon']" class="w-3.5 h-3.5" />
                                    @endif
                                    {{ $action['label'] ?? $action }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                @foreach ($messages as $msg)
                    @include('filament-copilot::components.chat-message', ['msg' => $msg])
                @endforeach
            @endif

            {{-- SSE Streaming content (while actively receiving chunks) --}}
            <template x-if="isStreaming && streamedContent">
                <div class="flex items-start gap-3">
                    <div
                        class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                        <x-filament::icon icon="heroicon-o-sparkles"
                            class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div
                        class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                        <div class="text-sm prose prose-sm dark:prose-invert max-w-none wrap-break-word">
                            <p x-text="streamedContent" class="whitespace-pre-wrap"></p>
                        </div>
                        <span
                            class="inline-block w-1.5 h-4 bg-primary-500 animate-pulse rounded-sm ml-0.5 align-text-bottom"></span>
                    </div>
                </div>
            </template>

            {{-- Keep streamed content visible while Livewire re-renders --}}
            <template x-if="pendingComplete && streamedContent">
                <div class="flex items-start gap-3">
                    <div
                        class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                        <x-filament::icon icon="heroicon-o-sparkles"
                            class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div
                        class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                        <div class="text-sm prose prose-sm dark:prose-invert max-w-none wrap-break-word">
                            <p x-text="streamedContent" class="whitespace-pre-wrap"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Loading indicator (synchronous fallback) --}}
            <div wire:loading wire:target="sendMessage" x-show="!isStreaming" class="flex items-start gap-3">
                <div
                    class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                    <x-filament::icon icon="heroicon-o-sparkles"
                        class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex items-center gap-1.5 py-2">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                        style="animation-delay: 0ms"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                        style="animation-delay: 150ms"></span>
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                        style="animation-delay: 300ms"></span>
                </div>
            </div>

            {{-- Streaming bouncing dots (before first chunk arrives) --}}
            <template x-if="isStreaming && !streamedContent">
                <div class="flex items-start gap-3">
                    <div
                        class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                        <x-filament::icon icon="heroicon-o-sparkles"
                            class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div class="flex items-center gap-1.5 py-2">
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                            style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                            style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"
                            style="animation-delay: 300ms"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- AskUser Question UI --}}
    @if ($pendingQuestion)
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/10">
            <div class="flex items-start gap-2 mb-2">
                <x-filament::icon icon="heroicon-o-question-mark-circle"
                    class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        {{ __('filament-copilot::filament-copilot.question_from_copilot') }}
                    </p>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        {{ $pendingQuestion['question'] ?? '' }}
                    </p>
                    @if (!empty($pendingQuestion['context']))
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 italic">
                            {{ $pendingQuestion['context'] }}
                        </p>
                    @endif
                    @if (!empty($pendingQuestion['options']))
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach ($pendingQuestion['options'] as $option)
                                <button wire:click="respondToQuestion('{{ addslashes($option) }}')" type="button"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-blue-200 dark:border-blue-700 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800 transition">
                                    {{ $option }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Plan Approval --}}
    @if ($pendingPlan)
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/10">
            <div class="flex items-start gap-2 mb-2">
                <x-filament::icon icon="heroicon-o-clipboard-document-list"
                    class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                        @if (!empty($pendingPlan['executing']))
                            {{ __('filament-copilot::filament-copilot.plan_executing') }}
                            <span class="text-xs font-normal ml-1">
                                ({{ $pendingPlan['current_step'] ?? 0 }}/{{ $pendingPlan['total_steps'] ?? count($pendingPlan['steps'] ?? []) }})
                            </span>
                        @else
                            {{ __('filament-copilot::filament-copilot.plan_proposed') }}
                        @endif
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                        {{ $pendingPlan['description'] ?? '' }}
                    </p>
                    @if (!empty($pendingPlan['steps']))
                        <ol class="mt-2 space-y-1">
                            @foreach ($pendingPlan['steps'] as $i => $step)
                                @php
                                    $stepClass = 'text-amber-800 dark:text-amber-300';
                                    if (!empty($pendingPlan['executing']) && $i < ($pendingPlan['current_step'] ?? 0)) {
                                        $stepClass = 'text-green-600 dark:text-green-400 line-through';
                                    } elseif (
                                        !empty($pendingPlan['executing']) &&
                                        $i === ($pendingPlan['current_step'] ?? 0)
                                    ) {
                                        $stepClass = 'text-amber-800 dark:text-amber-200 font-medium';
                                    }
                                @endphp
                                <li class="text-xs flex items-center gap-1.5 {{ $stepClass }}">
                                    @if (!empty($pendingPlan['executing']) && $i < ($pendingPlan['current_step'] ?? 0))
                                        <x-filament::icon icon="heroicon-o-check-circle"
                                            class="w-3.5 h-3.5 text-green-500" />
                                    @elseif (!empty($pendingPlan['executing']) && $i === ($pendingPlan['current_step'] ?? 0))
                                        <span class="w-3.5 h-3.5 flex items-center justify-center">
                                            <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                                        </span>
                                    @else
                                        <span
                                            class="w-3.5 h-3.5 flex items-center justify-center text-amber-400">{{ $i + 1 }}.</span>
                                    @endif
                                    {{ $step['description'] ?? $step }}
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
            @if (empty($pendingPlan['executing']))
                <div class="flex gap-2 justify-end">
                    <x-filament::button wire:click="rejectPlan('{{ $pendingPlan['id'] }}')" color="danger"
                        size="xs">
                        {{ __('filament-copilot::filament-copilot.reject') }}
                    </x-filament::button>
                    <x-filament::button wire:click="approvePlan('{{ $pendingPlan['id'] }}')" color="success"
                        size="xs">
                        {{ __('filament-copilot::filament-copilot.approve') }}
                    </x-filament::button>
                </div>
            @endif
        </div>
    @endif

    {{-- Input --}}
    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 shrink-0">
        <form wire:submit="sendMessage" class="flex items-end gap-2">
            <textarea wire:model="message" @keydown="handleKeydown($event)" @input="autoResize($event.target)" rows="1"
                class="flex-1 resize-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-gray-400 dark:placeholder-gray-500"
                style="max-height: 120px" placeholder="{{ __('filament-copilot::filament-copilot.input_placeholder') }}"
                :disabled="$wire.isLoading || isStreaming"></textarea>
            <button type="submit"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                :disabled="$wire.isLoading || isStreaming">
                <template x-if="!isStreaming && !$wire.isLoading">
                    <x-filament::icon icon="heroicon-o-paper-airplane" class="w-4 h-4" />
                </template>
                <template x-if="isStreaming || $wire.isLoading">
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </template>
            </button>
        </form>
    </div>
</div>
