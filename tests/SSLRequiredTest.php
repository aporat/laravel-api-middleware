<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\Exceptions\SslRequiredException;
use Aporat\Laravel\ApiMiddleware\SSLRequired;
use Illuminate\Http\Request;

final class SSLRequiredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        $this->app['config']->set('app.env', 'production');
    }

    private function insecure(string $uri): Request
    {
        return Request::create('http://example.com'.$uri, 'GET', [], [], [], [
            'HTTPS' => 'off',
            'SERVER_PORT' => 80,
        ]);
    }

    public function test_a_secure_request_passes(): void
    {
        $request = Request::create('https://example.com/test', 'GET', [], [], [], [
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ]);

        $response = (new SSLRequired)->handle($request, fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());
    }

    public function test_an_insecure_request_is_rejected(): void
    {
        $this->expectException(SslRequiredException::class);

        try {
            (new SSLRequired)->handle($this->insecure('/test'), fn () => response('OK'));
        } catch (SslRequiredException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('SSL Required', $e->getMessage());

            throw $e;
        }
    }

    public function test_the_rejection_status_is_configurable(): void
    {
        try {
            (new SSLRequired(status: 426))->handle($this->insecure('/test'), fn () => response('OK'));
            $this->fail('Expected SslRequiredException.');
        } catch (SslRequiredException $e) {
            $this->assertSame(426, $e->getStatusCode());
        }
    }

    public function test_an_exempt_route_passes(): void
    {
        $response = (new SSLRequired)->handle($this->insecure('/ping'), fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());
    }

    public function test_an_exempt_route_still_matches_with_a_query_string(): void
    {
        // Regression: getRequestUri() includes the query string, so "/ping?v=1"
        // never matched the "/ping" exemption.
        $response = (new SSLRequired)->handle($this->insecure('/ping?v=1'), fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());
    }

    public function test_exempt_routes_support_wildcards_and_leading_slashes(): void
    {
        $middleware = new SSLRequired(['/health/*'], []);

        $response = $middleware->handle($this->insecure('/health/db'), fn () => response('OK'));
        $this->assertSame('OK', $response->getContent());

        $this->expectException(SslRequiredException::class);
        $middleware->handle($this->insecure('/health'), fn () => response('OK'));
    }

    public function test_an_exempt_environment_passes(): void
    {
        $this->app['env'] = 'local';

        $response = (new SSLRequired)->handle($this->insecure('/test'), fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());
    }

    public function test_an_empty_exemption_list_rejects_everything_insecure(): void
    {
        $this->expectException(SslRequiredException::class);

        (new SSLRequired([], []))->handle($this->insecure('/ping'), fn () => response('OK'));
    }

    public function test_a_request_forwarded_as_https_by_a_trusted_proxy_passes(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8'], Request::HEADER_X_FORWARDED_AWS_ELB);

        $request = Request::create('http://example.com/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $response = (new SSLRequired)->handle($request, fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());
    }

    public function test_the_middleware_alias_is_registered(): void
    {
        $this->assertSame(
            SSLRequired::class,
            $this->app['router']->getMiddleware()['ssl.required'] ?? null,
        );
    }
}
