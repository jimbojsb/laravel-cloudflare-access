<?php

namespace Jimbojsb\CloudflareAccess;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CloudflareAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cloudflare-access.php', 'cloudflare-access');

        $this->app->singleton(CloudflareAccessJWT::class, function (Application $app) {
            return new CloudflareAccessJWT(
                $app['config']->get('cloudflare-access.subdomain'),
                $app['config']->get('cloudflare-access.audience'),
                $app['config']->get('cloudflare-access.jwk_cache_minutes', 60)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/cloudflare-access.php' => config_path('cloudflare-access.php'),
        ], 'cloudflare-access-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_cloudflare_access_users_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_cloudflare_access_users_table.php'),
        ], 'cloudflare-access-migrations');
    }
}
