<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\TrustProxies;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class TrustProxiesTest extends TestCase
{
    /**
     * @param  array<string, string>  $server
     */
    private function request(array $server = []): Request
    {
        return Request::create('http://example.com/test', 'GET', [], [], [], array_merge([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_HOST' => 'forwarded.example.net',
        ], $server));
    }

    public function test_forwarded_headers_are_honoured_for_a_trusted_proxy(): void
    {
        $request = $this->request();

        $response = (new TrustProxies(['10.0.0.0/8']))->handle($request, fn () => response('OK'));

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertTrue($request->secure());
        $this->assertSame('OK', $response->getContent());
    }

    public function test_forwarded_headers_are_ignored_for_an_untrusted_proxy(): void
    {
        $request = $this->request();

        (new TrustProxies(['192.0.2.1']))->handle($request, fn () => response('OK'));

        $this->assertSame('10.0.0.5', $request->ip());
        $this->assertFalse($request->secure());
    }

    public function test_proxies_fall_back_to_configuration(): void
    {
        config()->set('api-middleware.trust_proxies.proxies', ['10.0.0.0/8']);

        $request = $this->request();

        (new TrustProxies)->handle($request, fn () => response('OK'));

        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_the_configured_header_set_is_applied(): void
    {
        // The default AWS ELB set deliberately excludes X-Forwarded-Host.
        $request = $this->request();
        (new TrustProxies(['10.0.0.0/8']))->handle($request, fn () => response('OK'));
        $this->assertSame('example.com', $request->getHost());

        $request = $this->request();
        (new TrustProxies(['10.0.0.0/8'], ['x_forwarded_for', 'x_forwarded_host']))
            ->handle($request, fn () => response('OK'));
        $this->assertSame('forwarded.example.net', $request->getHost());
    }

    public function test_header_set_accepts_a_raw_bitmask_and_a_laravel_style_name(): void
    {
        $request = $this->request();
        (new TrustProxies(['10.0.0.0/8'], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_HOST))
            ->handle($request, fn () => response('OK'));
        $this->assertSame('forwarded.example.net', $request->getHost());

        $request = $this->request();
        (new TrustProxies(['10.0.0.0/8'], 'HEADER_X_FORWARDED_AWS_ELB'))
            ->handle($request, fn () => response('OK'));
        $this->assertSame('example.com', $request->getHost());
        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_unknown_header_set_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrustProxies(['10.0.0.0/8'], 'x_forwarded_nonsense');
    }

    public function test_a_comma_separated_proxy_list_is_accepted(): void
    {
        $request = $this->request();

        (new TrustProxies(' 192.0.2.1 , 10.0.0.0/8 '))->handle($request, fn () => response('OK'));

        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_wildcard_trusts_the_calling_ip(): void
    {
        $request = $this->request(['REMOTE_ADDR' => '198.51.100.7']);

        (new TrustProxies('*'))->handle($request, fn () => response('OK'));

        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_remote_addr_sentinel_trusts_the_connecting_peer(): void
    {
        $request = $this->request(['REMOTE_ADDR' => '198.51.100.7']);

        (new TrustProxies(['REMOTE_ADDR']))->handle($request, fn () => response('OK'));

        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_the_middleware_alias_is_registered(): void
    {
        $this->assertSame(
            TrustProxies::class,
            $this->app['router']->getMiddleware()['trust.proxies'] ?? null,
        );
    }
}
