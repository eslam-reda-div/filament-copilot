<?php

namespace EslamRedaDiv\FilamentCopilot\Tests;

use EslamRedaDiv\FilamentCopilot\FilamentCopilotServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentCopilotServiceProvider::class,
        ];
    }
}
