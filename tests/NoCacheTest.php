<?php

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\NoCache;
use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NoCacheTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider'];
    }

    public function test_no_cache_headers_are_applied()
    {
        $request = Request::create('/test', 'GET');
        $middleware = new NoCache();

        $response = $middleware->handle($request, fn($req) => new JsonResponse(['data' => 'test']));

        $this->assertInstanceOf(JsonResponse::class, $response);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('post-check=0', $cacheControl);
        $this->assertStringContainsString('pre-check=0', $cacheControl);

        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
    }

    public function test_custom_headers_are_applied()
    {
        $request = Request::create('/test', 'GET');
        $middleware = new NoCache('no-cache, max-age=0', 'no-store');

        $response = $middleware->handle($request, fn($req) => response('OK'));

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $this->assertEquals('no-store', $response->headers->get('Pragma'));
    }
}