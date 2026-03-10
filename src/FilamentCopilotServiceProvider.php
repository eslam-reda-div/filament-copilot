<?php

namespace EslamRedaDiv\FilamentCopilot;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
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
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        // Asset Registration
        // FilamentAsset::register(
        //     assets: [
        //         // Css::make('filament-copilot', __DIR__ . '/../resources/dist/filament-copilot.css')->loadedOnRequest(),
        //         // Js::make('filament-copilot', __DIR__ . '/../resources/dist/filament-copilot.js'),
        //         // AlpineComponent::make('filament-copilot', __DIR__ . '/../resources/dist/components/filament-copilot.js'),
        //     ],
        //     package: 'eslam-reda-div/filament-copilot'
        // );
    }
}
