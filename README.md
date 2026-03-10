# Filament Copilot

A Filament plugin for AI-powered copilot features.

## Installation

You can install the package via composer:

```bash
composer require eslam-reda-div/filament-copilot
```

## Usage

Register the plugin in your Filament panel provider:

```php
use EslamRedaDiv\FilamentCopilot\FilamentCopilotPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentCopilotPlugin::make());
}
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
