<?php

namespace EslamRedaDiv\FilamentCopilot;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentCopilotPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-copilot';
    }

    public function register(Panel $panel): void
    {
        // Register resources, pages, widgets, etc.
        // $panel
        //     ->resources([])
        //     ->pages([])
        //     ->widgets([]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
