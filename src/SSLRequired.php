<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Aporat\Laravel\ApiMiddleware\Exceptions\SslRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects plain-HTTP requests, except on exempt paths or in exempt environments.
 *
 * Runs after {@see TrustProxies} so that `X-Forwarded-Proto` from a terminating
 * load balancer is taken into account; without it every request behind TLS
 * termination looks insecure.
 */
final class SSLRequired
{
    /** @var array<int, string> */
    private const DEFAULT_EXCEPT_ROUTES = ['ping'];

    /** @var array<int, string> */
    private const DEFAULT_EXCEPT_ENVIRONMENTS = ['local', 'development', 'testing'];

    /** @var array<int, string> */
    private readonly array $exceptRoutes;

    /** @var array<int, string> */
    private readonly array $exceptEnvironments;

    private readonly int $status;

    /**
     * @param  array<int, string>|null  $exceptRoutes  Path patterns (leading slash optional,
     *                                                 `*` wildcards supported, e.g. `health/*`).
     * @param  array<int, string>|null  $exceptEnvironments  Environment name patterns.
     */
    public function __construct(?array $exceptRoutes = null, ?array $exceptEnvironments = null, ?int $status = null)
    {
        $this->exceptRoutes = self::normalizeRoutes(
            $exceptRoutes ?? config('api-middleware.ssl_required.except_routes', self::DEFAULT_EXCEPT_ROUTES) ?? []
        );

        $this->exceptEnvironments = array_values(array_filter(
            $exceptEnvironments ?? config('api-middleware.ssl_required.except_environments', self::DEFAULT_EXCEPT_ENVIRONMENTS) ?? [],
            static fn ($environment): bool => is_string($environment) && $environment !== '',
        ));

        $this->status = $status ?? (int) config('api-middleware.ssl_required.status', 403);
    }

    /**
     * @throws SslRequiredException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && ! $this->isExempt($request)) {
            throw new SslRequiredException(status: $this->status);
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($this->exceptEnvironments !== [] && app()->environment($this->exceptEnvironments)) {
            return true;
        }

        // `$request->is()` matches the decoded path without the query string —
        // `getRequestUri()` includes it, so `/ping?v=1` used to miss the exemption.
        return $this->exceptRoutes !== [] && $request->is(...$this->exceptRoutes);
    }

    /**
     * @param  array<int, mixed>  $routes
     * @return array<int, string>
     */
    private static function normalizeRoutes(array $routes): array
    {
        $normalized = [];

        foreach ($routes as $route) {
            if (! is_string($route)) {
                continue;
            }

            $trimmed = trim($route, '/');
            $normalized[] = $trimmed === '' ? '/' : $trimmed;
        }

        return $normalized;
    }
}
