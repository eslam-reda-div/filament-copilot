<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot;

use EslamRedaDiv\FilamentCopilot\Commands\InstallCommand;
use EslamRedaDiv\FilamentCopilot\Commands\MakeCopilotToolCommand;
use EslamRedaDiv\FilamentCopilot\Livewire\ConversationSidebar;
use EslamRedaDiv\FilamentCopilot\Livewire\CopilotButton;
use EslamRedaDiv\FilamentCopilot\Livewire\CopilotChat;
use EslamRedaDiv\FilamentCopilot\Services\ConversationManager;
use EslamRedaDiv\FilamentCopilot\Services\ExportService;
use EslamRedaDiv\FilamentCopilot\Services\RateLimitService;
use EslamRedaDiv\FilamentCopilot\Services\ToolRegistry;
use EslamRedaDiv\FilamentCopilot\Translation\EnglishFallbackLoader;
use Filament\Support\Assets\Css;
use Illuminate\Contracts\Translation\Loader;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentCopilotServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-copilot';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasTranslations()
            ->hasConfigFile()
            ->hasRoute('web')
            ->discoversMigrations()
            ->hasCommand(InstallCommand::class)
            ->hasCommand(MakeCopilotToolCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RateLimitService::class);
        $this->app->singleton(ConversationManager::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ExportService::class);

        // Fall back to the package's English strings instead of rendering raw
        // translation keys when neither the current nor the app's fallback
        // locale ships them.
        $this->app->extend('translation.loader', fn (Loader $loader, $app) => $loader instanceof EnglishFallbackLoader
            ? $loader
            : new EnglishFallbackLoader(
                $loader,
                static::$name,
                fn (): ?string => $app['config']->get('app.fallback_locale'),
            ));
    }

    public function packageBooted(): void
    {
        $aiMigrationsPath = base_path('vendor/laravel/ai/database/migrations');

        if (is_dir($aiMigrationsPath)) {
            $this->publishesMigrations([
                $aiMigrationsPath => database_path('migrations'),
            ], 'filament-copilot-ai-migrations');
        }

        FilamentAsset::register([
            Css::make('filament-copilot', __DIR__ . '/../resources/dist/filament-copilot.css'),
            Js::make('filament-copilot', __DIR__ . '/../resources/dist/filament-copilot.js'),
        ], 'eslam-reda-div/filament-copilot');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/filament-copilot'),
        ], 'filament-copilot-stubs');

        if (class_exists(Livewire::class) && $this->app->bound('livewire')) {
            Livewire::component('filament-copilot-chat', CopilotChat::class);
            Livewire::component('filament-copilot-button', CopilotButton::class);
            Livewire::component('filament-copilot-sidebar', ConversationSidebar::class);
        }
    }
}
