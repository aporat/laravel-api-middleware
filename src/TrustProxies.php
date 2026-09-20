<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trusts a configured set of reverse proxies so that the `X-Forwarded-*`
 * headers they add are honoured when resolving the client IP, scheme and port.
 */
final class TrustProxies
{
    /**
     * Catch-all IPv4/IPv6 ranges used when the proxy list is `*` or `**`.
     */
    private const TRUST_ALL = ['0.0.0.0/0', '::/0'];

    private const DEFAULT_PROXIES = ['127.0.0.1', '10.0.0.0/8'];

    /**
     * Named forwarding header sets, accepted in config in place of a raw bitmask.
     *
     * Keys are matched case-insensitively and with an optional `HEADER_` prefix,
     * so `x_forwarded_aws_elb` and `HEADER_X_FORWARDED_AWS_ELB` both work.
     */
    private const HEADER_SETS = [
        'forwarded' => SymfonyRequest::HEADER_FORWARDED,
        'x_forwarded_for' => SymfonyRequest::HEADER_X_FORWARDED_FOR,
        'x_forwarded_host' => SymfonyRequest::HEADER_X_FORWARDED_HOST,
        'x_forwarded_proto' => SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        'x_forwarded_port' => SymfonyRequest::HEADER_X_FORWARDED_PORT,
        'x_forwarded_prefix' => SymfonyRequest::HEADER_X_FORWARDED_PREFIX,
        'x_forwarded_aws_elb' => SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB,
        'x_forwarded_traefik' => SymfonyRequest::HEADER_X_FORWARDED_TRAEFIK,
    ];

    /**
     * @var array<int, string>|string
     */
    private readonly array|string $proxies;

    private readonly int $headers;

    /**
     * @param  array<int, string>|string|null  $proxies  IPs/CIDRs, a comma separated list,
     *                                                   `*` to trust the calling IP, or `null`
     *                                                   to read `api-middleware.trust_proxies.proxies`.
     * @param  int|string|array<int, string>|null  $headers  A `Request::HEADER_*` bitmask, a named
     *                                                       header set, a list of named sets, or `null`
     *                                                       to read `api-middleware.trust_proxies.headers`.
     */
    public function __construct(array|string|null $proxies = null, int|string|array|null $headers = null)
    {
        $this->proxies = $proxies
            ?? config('api-middleware.trust_proxies.proxies', self::DEFAULT_PROXIES)
            ?? self::DEFAULT_PROXIES;

        $this->headers = self::resolveHeaders(
            $headers ?? config('api-middleware.trust_proxies.headers')
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Laravel 13 always keeps its own TrustProxies in the global stack, and
        // it resets the trusted set to [] on every request. Register this one
        // with `$middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, ...)`
        // rather than appending it, so it takes the built-in's slot instead of
        // having to run after the reset.
        $request::setTrustedProxies($this->resolveProxies($request), $this->headers);

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function resolveProxies(Request $request): array
    {
        $proxies = $this->proxies;

        if ($proxies === '*' || $proxies === '**') {
            return self::TRUST_ALL;
        }

        if (is_string($proxies)) {
            $proxies = explode(',', $proxies);
        }

        $resolved = [];

        foreach ($proxies as $proxy) {
            if (! is_string($proxy)) {
                continue;
            }

            $proxy = trim($proxy);

            if ($proxy === 'REMOTE_ADDR') {
                $proxy = (string) $request->server->get('REMOTE_ADDR', '');
            }

            if ($proxy !== '') {
                $resolved[] = $proxy;
            }
        }

        return $resolved;
    }

    /**
     * @param  int|string|array<int, string>|null  $headers
     */
    private static function resolveHeaders(int|string|array|null $headers): int
    {
        if ($headers === null) {
            return SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB;
        }

        if (is_int($headers)) {
            return $headers;
        }

        $bitmask = 0;

        foreach ((array) $headers as $name) {
            $key = strtolower((string) $name);
            $key = str_starts_with($key, 'header_') ? substr($key, 7) : $key;

            if (! isset(self::HEADER_SETS[$key])) {
                throw new InvalidArgumentException("Unknown forwarded header set [{$name}].");
            }

            $bitmask |= self::HEADER_SETS[$key];
        }

        return $bitmask;
    }
}
