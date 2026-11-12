<?php

declare(strict_types=1);

namespace EslamRedaDiv\FilamentCopilot\Translation;

use Closure;
use Illuminate\Contracts\Translation\Loader;

/**
 * Wraps the application's translation loader so the package's own strings
 * never render as raw keys.
 *
 * Laravel falls back from the current locale to `app.fallback_locale` only.
 * When neither of them ships this package's strings (an app with
 * `fallback_locale = 'fr'`, say), every label used to render as
 * `filament-copilot::filament-copilot.some_key`. For the package's namespace
 * this loader layers the lines as: English, then the app's fallback locale,
 * then the requested locale — so a translated line always wins, and English
 * fills whatever is missing. Every other namespace is passed through as is.
 */
class EnglishFallbackLoader implements Loader
{
    public const BASE_LOCALE = 'en';

    /**
     * @param  Closure(): ?string  $fallbackLocale
     */
    public function __construct(
        protected Loader $loader,
        protected string $namespace,
        protected Closure $fallbackLocale,
    ) {}

    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($namespace !== $this->namespace || $group === '*') {
            return $lines;
        }

        $layers = array_unique(array_filter([
            static::BASE_LOCALE,
            ($this->fallbackLocale)(),
        ]));

        $merged = [];

        foreach ($layers as $layer) {
            if ($layer !== $locale) {
                $merged = array_replace_recursive($merged, $this->loader->load($layer, $group, $namespace));
            }
        }

        return array_replace_recursive($merged, $lines);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->loader->namespaces();
    }

    public function getWrappedLoader(): Loader
    {
        return $this->loader;
    }

    /**
     * Forward anything else (e.g. FileLoader::addPath()) to the wrapped loader.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->loader->{$method}(...$parameters);
    }
}
