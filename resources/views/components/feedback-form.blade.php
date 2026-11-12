{{-- Detailed thumbs-down form (config: filament-copilot.feedback.detailed).
     Rendered inside the chat component, so $feedbackMessageId and the
     feedback* properties come from CopilotChat. --}}
@if ($feedbackMessageId)
    <div wire:key="copilot-feedback-form-{{ $feedbackMessageId }}" data-copilot-feedback-form
        x-on:keydown.escape.stop="$wire.closeFeedbackForm()"
        class="absolute inset-0 z-30 flex items-end sm:items-center justify-center p-4 bg-gray-950/40 dark:bg-gray-950/60">
        <form wire:submit="submitFeedback" role="dialog" aria-modal="true" aria-labelledby="copilot-feedback-title"
            class="w-full max-w-md max-h-full overflow-y-auto rounded-xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 id="copilot-feedback-title" class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('filament-copilot::filament-copilot.feedback_form_title') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('filament-copilot::filament-copilot.feedback_form_description') }}</p>
                </div>
                <button type="button" wire:click="closeFeedbackForm"
                    class="flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 hover:bg-gray-500/5 dark:hover:bg-gray-400/5 shrink-0"
                    title="{{ __('filament-copilot::filament-copilot.close') }}"
                    aria-label="{{ __('filament-copilot::filament-copilot.close') }}">
                    <x-filament::icon icon="heroicon-o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <fieldset>
                <legend class="text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ __('filament-copilot::filament-copilot.feedback_category') }}</legend>
                <div class="mt-2 grid gap-1.5">
                    @foreach (\EslamRedaDiv\FilamentCopilot\Enums\FeedbackCategory::cases() as $category)
                        <label
                            class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer text-sm text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 has-checked:border-primary-500 has-checked:bg-primary-50 dark:has-checked:bg-primary-950/30">
                            <input type="radio" name="copilot-feedback-category" wire:model="feedbackCategory"
                                value="{{ $category->value }}" class="text-primary-600 focus:ring-primary-500">
                            <span>{{ $category->getLabel() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('feedbackCategory')
                    <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </fieldset>

            <div>
                <label for="copilot-feedback-expected" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ __('filament-copilot::filament-copilot.feedback_expected_answer') }}</label>
                <textarea id="copilot-feedback-expected" wire:model="feedbackExpectedAnswer" rows="3" maxlength="5000"
                    placeholder="{{ __('filament-copilot::filament-copilot.feedback_expected_answer_placeholder') }}"
                    class="mt-1 block w-full resize-y rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                @error('feedbackExpectedAnswer')
                    <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100 cursor-pointer">
                <input type="checkbox" wire:model="feedbackSuggestsNewTool"
                    class="rounded text-primary-600 focus:ring-primary-500">
                <span>{{ __('filament-copilot::filament-copilot.feedback_suggests_new_tool') }}</span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <x-filament::button type="button" size="sm" color="gray" wire:click="skipFeedbackDetails">
                    {{ __('filament-copilot::filament-copilot.feedback_skip_details') }}
                </x-filament::button>
                <x-filament::button type="submit" size="sm" wire:target="submitFeedback">
                    {{ __('filament-copilot::filament-copilot.feedback_submit') }}
                </x-filament::button>
            </div>
        </form>
    </div>
@endif
