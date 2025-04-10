<?php

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\SSLRequired;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

class SSLRequiredTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider'];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.env', 'production');
    }

    public function test_ssl_required_passes_on_secure_request()
    {
        $request = Request::create('https://example.com/test', 'GET', [], [], [], ['HTTPS' => 'on', 'SERVER_PORT' => 443]);
        $middleware = new SSLRequired;

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_ssl_required_allows_exempt_route()
    {
        $request = Request::create('http://example.com/ping', 'GET', [], [], [], ['HTTPS' => 'off', 'SERVER_PORT' => 80]);
        $middleware = new SSLRequired;

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals('OK', $response->getContent());
    }

    public function test_ssl_required_allows_exempt_environment()
    {
        $this->app['env'] = 'testing';
        $request = Request::create('http://example.com/test', 'GET', [], [], [], ['HTTPS' => 'off', 'SERVER_PORT' => 80]);
        $middleware = new SSLRequired;

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals('OK', $response->getContent());
    }
}
