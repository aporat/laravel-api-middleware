<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class ApiMiddlewareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-middleware.php', 'api-middleware');
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('trust.proxies', TrustProxies::class);
        $router->aliasMiddleware('no.cache', NoCache::class);
        $router->aliasMiddleware('ssl.required', SSLRequired::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-middleware.php' => config_path('api-middleware.php'),
            ], ['config', 'api-middleware-config']);
        }
    }
}
