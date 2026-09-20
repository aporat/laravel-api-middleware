<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps cache-defeating headers on every response so that API payloads are
 * never stored by browsers, proxies or CDNs.
 */
final class NoCache
{
    private const DEFAULT_CACHE_CONTROL = 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0';

    private const DEFAULT_PRAGMA = 'no-cache';

    private const DEFAULT_EXPIRES = '0';

    private readonly string $cacheControl;

    private readonly ?string $pragma;

    private readonly ?string $expires;

    /**
     * A `null` argument falls back to config; a `null` *config* value omits the
     * header entirely (and strips one an upstream response already set).
     */
    public function __construct(?string $cacheControl = null, ?string $pragma = null, ?string $expires = null)
    {
        $this->cacheControl = $cacheControl
            ?? config('api-middleware.no_cache.cache_control', self::DEFAULT_CACHE_CONTROL)
            ?? self::DEFAULT_CACHE_CONTROL;

        $this->pragma = $pragma ?? config('api-middleware.no_cache.pragma', self::DEFAULT_PRAGMA);
        $this->expires = $expires ?? config('api-middleware.no_cache.expires', self::DEFAULT_EXPIRES);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Symfony's ResponseHeaderBag keeps a parsed copy of Cache-Control, so
        // set() (not add()) is required to genuinely replace what a route set.
        // It also re-serialises the directives: they come back alphabetised and
        // with `private` appended unless `public`/`s-maxage` is present, so the
        // configured string is normalised rather than emitted verbatim.
        $response->headers->set('Cache-Control', $this->cacheControl);

        $this->apply($response, 'Pragma', $this->pragma);
        $this->apply($response, 'Expires', $this->expires);

        return $response;
    }

    private function apply(Response $response, string $header, ?string $value): void
    {
        if ($value === null) {
            $response->headers->remove($header);

            return;
        }

        $response->headers->set($header, $value);
    }
}
