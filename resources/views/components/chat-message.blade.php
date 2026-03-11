@php
    $isUser = ($msg['role'] ?? '') === 'user';
    $isSystem = ($msg['role'] ?? '') === 'system';
    $isAssistant = ($msg['role'] ?? '') === 'assistant';
    $isTool = ($msg['role'] ?? '') === 'tool';
@endphp

@if ($isUser)
    <div class="flex items-start gap-3 justify-end">
        <div class="max-w-[85%] rounded-lg px-3 py-2 bg-primary-600 text-white">
            <p class="text-sm whitespace-pre-wrap wrap-break-word">{{ $msg['content'] }}</p>
        </div>
        <div class="w-7 h-7 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center shrink-0">
            <x-filament::icon icon="heroicon-o-user" class="w-4 h-4 text-gray-600 dark:text-gray-300" />
        </div>
    </div>
@elseif($isAssistant)
    <div class="flex items-start gap-3">
        <div
            class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
            <x-filament::icon icon="heroicon-o-sparkles" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
        </div>
        <div class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            <div class="text-sm prose prose-sm dark:prose-invert max-w-none wrap-break-word">
                {!! \Illuminate\Support\Str::markdown($msg['content'] ?? '') !!}
            </div>
        </div>
    </div>
@elseif($isSystem)
    <div class="flex justify-center">
        <div
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-3.5 h-3.5" />
            <span class="text-xs">{{ $msg['content'] }}</span>
        </div>
    </div>
@elseif($isTool)
    <div class="flex items-start gap-3">
        <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center shrink-0">
            <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="w-4 h-4 text-gray-500" />
        </div>
        <div
            class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $msg['content'] }}</p>
        </div>
    </div>
@endif
