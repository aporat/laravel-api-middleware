<?php

namespace Aporat\Laravel\ApiMiddleware;

use Illuminate\Support\ServiceProvider;

class ApiMiddlewareServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app['router']->aliasMiddleware('trust.proxies', TrustProxies::class);
        $this->app['router']->aliasMiddleware('no.cache', NoCache::class);
        $this->app['router']->aliasMiddleware('ssl.required', SSLRequired::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/api-middleware.php' => config_path('api-middleware.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api-middleware.php', 'api-middleware');
    }
}