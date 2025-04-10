<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NoCache
{
    protected string $cacheControl = 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0';
    protected string $pragma = 'no-cache';

    public function __construct(?string $cacheControl = null, ?string $pragma = null)
    {
        $this->cacheControl = $cacheControl ?? config('api-middleware.no_cache.cache_control', $this->cacheControl);
        $this->pragma = $pragma ?? config('api-middleware.no_cache.pragma', $this->pragma);
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', $this->cacheControl);
        $response->headers->set('Pragma', $this->pragma);

        return $response;
    }
}