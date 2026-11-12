<?php

use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use EslamRedaDiv\FilamentCopilot\Models\CopilotMessage;
use EslamRedaDiv\FilamentCopilot\Services\ExportService;
use EslamRedaDiv\FilamentCopilot\Translation\EnglishFallbackLoader;
use Illuminate\Support\Facades\Blade;

/*
 * Issue #38: ship a German translation, route the remaining hard-coded
 * strings through the language files, and never render raw keys.
 */

function packageLangPath(string $locale): string
{
    return __DIR__."/../../resources/lang/{$locale}/filament-copilot.php";
}

/**
 * @return array<int, string>
 */
function shippedLocales(): array
{
    return array_map('basename', glob(__DIR__.'/../../resources/lang/*', GLOB_ONLYDIR));
}

it('ships a german translation', function () {
    expect(shippedLocales())->toContain('en', 'de');
});

it('translates every english key in every shipped locale', function (string $locale) {
    $english = require packageLangPath('en');
    $translation = require packageLangPath($locale);

    expect(array_keys(array_diff_key($english, $translation)))->toBe([], "Missing in {$locale}")
        ->and(array_keys(array_diff_key($translation, $english)))->toBe([], "Unknown keys in {$locale}");

    foreach ($english as $key => $line) {
        expect($translation[$key])->toBeString()->not->toBe('', "{$locale}.{$key} is empty");

        // Placeholders such as :ref and :count must survive translation.
        preg_match_all('/:([a-z_]+)/', $line, $expected);
        foreach ($expected[1] as $placeholder) {
            expect($translation[$key])->toContain(":{$placeholder}");
        }
    }
})->with(fn () => array_values(array_diff(shippedLocales(), ['en'])));

it('renders german strings when the locale is de', function () {
    app()->setLocale('de');

    expect(__('filament-copilot::filament-copilot.approve'))->toBe('Genehmigen')
        ->and(__('filament-copilot::filament-copilot.approval_required'))->toBe('Genehmigung erforderlich')
        ->and(__('filament-copilot::filament-copilot.error_occurred'))->toBe('Ein Fehler ist aufgetreten');
});

it('falls back to english instead of raw keys for a locale the package does not ship', function () {
    config()->set('app.fallback_locale', 'fr');
    app()->setLocale('fr');

    expect(__('filament-copilot::filament-copilot.error_occurred'))->toBe('An error occurred')
        ->and(__('filament-copilot::filament-copilot.stream_error', ['ref' => 'abc123']))
        ->toBe('Something went wrong. Please try again. (ref: abc123)');
});

it('prefers the app fallback locale over english', function () {
    config()->set('app.fallback_locale', 'de');
    app()->setLocale('de_AT');

    expect(__('filament-copilot::filament-copilot.approve'))->toBe('Genehmigen');
});

it('keeps app overrides published to lang/vendor', function () {
    $override = lang_path('vendor/filament-copilot/de');
    @mkdir($override, 0777, true);
    file_put_contents($override.'/filament-copilot.php', "<?php return ['approve' => 'Ausführen'];");

    try {
        app('translator')->setLoaded([]);
        app()->setLocale('de');

        expect(__('filament-copilot::filament-copilot.approve'))->toBe('Ausführen')
            ->and(__('filament-copilot::filament-copilot.reject'))->toBe('Ablehnen');
    } finally {
        @unlink($override.'/filament-copilot.php');
        @rmdir($override);
    }
});

it('leaves other translation namespaces untouched', function () {
    config()->set('app.fallback_locale', 'fr');
    app()->setLocale('fr');

    expect(app('translation.loader'))->toBeInstanceOf(EnglishFallbackLoader::class)
        ->and(__('validation.required'))->toBe('validation.required');
});

it('has no hard-coded english labels left in the chat views', function () {
    $views = [
        'livewire/copilot-chat.blade.php',
        'components/chat-message.blade.php',
        'livewire/conversation-sidebar.blade.php',
    ];

    foreach ($views as $view) {
        $source = file_get_contents(__DIR__.'/../../resources/views/'.$view);

        foreach (['>Arguments<', '>Result<', '>Error<', 'Approval required', '>Approve<', '>Reject<', "?? 'Close'", "?? 'Tool'"] as $literal) {
            expect($source)->not->toContain($literal, "{$view} still contains {$literal}");
        }

        expect(preg_match('/^\s*(send|new line) ?(&middot;)?\s*$/m', $source))->toBe(0, "{$view} has an untranslated keyboard hint");
    }
});

it('renders the tool call labels in german', function () {
    app()->setLocale('de');

    $html = view('filament-copilot::components.chat-message', ['msg' => [
        'role' => 'tool_call',
        'tool_name' => 'list_orders',
        'arguments' => ['status' => 'paid'],
        'result' => '3 orders',
        'success' => false,
        'error' => 'Zeitüberschreitung',
        'content' => '3 orders',
    ]])->render();

    expect($html)->toContain('Argumente')->toContain('Ergebnis')->toContain('Fehler')
        ->not->toContain('>Arguments<');
});

it('renders the approval card title and buttons in german', function () {
    app()->setLocale('de');

    $source = file_get_contents(__DIR__.'/../../resources/views/livewire/copilot-chat.blade.php');
    preg_match('/\{\{-- Human approval requests --\}\}(.*?)\{\{-- SSE Streaming content/s', $source, $matches);

    $html = Blade::render($matches[1]);

    expect($html)->toContain('Genehmigung erforderlich')
        ->toContain('Genehmigen')
        ->toContain('Ablehnen');
});

it('translates conversation errors sent over the stream', function () {
    app()->setLocale('de');

    $user = createTestUser();
    copilotTestSwapFilament($user);
    copilotTestFakeAgent([]);

    $body = copilotTestRunStream(['message' => 'Hallo', 'conversation_id' => '01JUNKNOWNCONVERSATION0000']);

    expect($body)->toContain('Unterhaltung nicht gefunden.');
});

it('exports conversations with translated labels', function () {
    app()->setLocale('de');

    $user = createTestUser();
    $conversation = CopilotConversation::create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'panel_id' => 'admin',
        'title' => 'Bestellungen',
    ]);

    CopilotMessage::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Wie viele?', 'input_tokens' => 3]);
    CopilotMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Drei.', 'output_tokens' => 4]);
    CopilotMessage::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => '']);

    $markdown = app(ExportService::class)->toMarkdown($conversation->id, $user, 'admin');

    expect($markdown)->toContain('**Datum:**')
        ->toContain('**Du:**')
        ->toContain('**Copilot:**')
        ->toContain('Verbrauchte Tokens insgesamt: 7')
        // The empty assistant message of a tool-only turn is skipped.
        ->and(substr_count($markdown, '**Copilot:**'))->toBe(1);
});
