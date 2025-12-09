<?php

namespace Jimbojsb\CloudflareAccess\Tests;

use Jimbojsb\CloudflareAccess\CloudflareAccessServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CloudflareAccessServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cloudflare-access.subdomain', 'testcompany');
        $app['config']->set('cloudflare-access.audience', 'test-audience-id');
        $app['config']->set('cloudflare-access.jwk_cache_minutes', 60);
        $app['config']->set('cloudflare-access.allow_local_user', true);
        $app['config']->set('cloudflare-access.user_model', \Jimbojsb\CloudflareAccess\Tests\Fixtures\User::class);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', [\Jimbojsb\CloudflareAccess\Http\Controllers\LoginController::class, 'login']);
    }
}
