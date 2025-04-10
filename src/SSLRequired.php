<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Aporat\Laravel\ApiMiddleware\Exceptions\SslRequiredException;
use Closure;
use Illuminate\Http\Request;

final class SSLRequired
{
    protected array $exceptRoutes = ['/ping'];

    protected array $exceptEnvironments = ['development', 'testing'];

    public function __construct(?array $exceptRoutes = null, ?array $exceptEnvironments = null)
    {
        $this->exceptRoutes = $exceptRoutes ?? config('api-middleware.ssl_required.except_routes', $this->exceptRoutes);
        $this->exceptEnvironments = $exceptEnvironments ?? config('api-middleware.ssl_required.except_environments', $this->exceptEnvironments);
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->isExempt($request) && ! $request->secure()) {
            throw new SslRequiredException('SSL Required', 403, $request);
        }

        return $next($request);
    }

    protected function isExempt(Request $request): bool
    {
        return in_array(app()->environment(), $this->exceptEnvironments) ||
            in_array($request->getRequestUri(), $this->exceptRoutes);
    }
}
