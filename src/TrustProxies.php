<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class TrustProxies
{
    protected array $proxies;

    protected int $headers = SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct($proxies = null)
    {
        $this->proxies = $proxies ?? config('api-middleware.trust_proxies.proxies', [
            '127.0.0.1',
            '10.0.0.0/24',
            '10.0.0.0/8',
        ]);
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $request->setTrustedProxies(
            (array) $this->proxies,
            $this->headers
        );

        return $next($request);
    }
}
