<?php

declare(strict_types=1);

namespace Gabrielesbaiz\GoogleTranslateToolkit\Tests;

use Gabrielesbaiz\GoogleTranslateToolkit\GoogleTranslateToolkitServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GoogleTranslateToolkitServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No test may ever reach the real API.
        Http::preventStrayRequests();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('google-translate-toolkit.api_key', 'test-key');
        $app['config']->set('google-translate-toolkit.default_source', null);
        $app['config']->set('google-translate-toolkit.default_target', 'it');
        $app['config']->set('google-translate-toolkit.cache.enabled', false);
        $app['config']->set('google-translate-toolkit.http.retry.times', 1);
    }
}
