<?php

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\TrustProxies;
use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;

class TrustProxiesTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider'];
    }

    public function test_trust_proxies_sets_configured_proxies()
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $middleware = new TrustProxies(['127.0.0.1', '10.0.0.0/24']);

        $response = $middleware->handle($request, fn($req) => response('OK'));

        $this->assertEquals('10.0.0.1', $request->ip());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_trust_proxies_uses_config_default()
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $middleware = new TrustProxies();

        $response = $middleware->handle($request, fn($req) => response('OK'));

        $this->assertEquals('127.0.0.1', $request->ip());
    }
}