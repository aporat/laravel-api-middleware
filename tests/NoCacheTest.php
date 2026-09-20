<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\NoCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class NoCacheTest extends TestCase
{
    /**
     * Symfony's ResponseHeaderBag re-parses Cache-Control, sorts the directives
     * and appends `private` unless `public`/`s-maxage` is present, so the
     * configured string is never emitted verbatim. Compare as a set.
     *
     * @return array<int, string>
     */
    private function directives(Response $response): array
    {
        $directives = array_map(trim(...), explode(',', (string) $response->headers->get('Cache-Control')));
        sort($directives);

        return $directives;
    }

    public function test_default_headers_are_applied(): void
    {
        $response = (new NoCache)->handle(
            Request::create('/test'),
            fn () => new JsonResponse(['data' => 'test']),
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(
            ['max-age=0', 'must-revalidate', 'no-cache', 'no-store', 'post-check=0', 'pre-check=0', 'private'],
            $this->directives($response),
        );
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }

    public function test_constructor_arguments_win_over_configuration(): void
    {
        $response = (new NoCache('no-cache, max-age=0', 'no-store', '-1'))
            ->handle(Request::create('/test'), fn () => response('OK'));

        $this->assertSame(['max-age=0', 'no-cache', 'private'], $this->directives($response));
        $this->assertSame('no-store', $response->headers->get('Pragma'));
        $this->assertSame('-1', $response->headers->get('Expires'));
    }

    public function test_configuration_is_used_when_no_arguments_are_given(): void
    {
        config()->set('api-middleware.no_cache.cache_control', 'no-store');
        config()->set('api-middleware.no_cache.pragma', 'no-cache');
        config()->set('api-middleware.no_cache.expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        $response = (new NoCache)->handle(Request::create('/test'), fn () => response('OK'));

        $this->assertSame(['no-store', 'private'], $this->directives($response));
        $this->assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->headers->get('Expires'));
    }

    public function test_null_configuration_omits_and_strips_the_header(): void
    {
        config()->set('api-middleware.no_cache.pragma', null);
        config()->set('api-middleware.no_cache.expires', null);

        $response = (new NoCache)->handle(Request::create('/test'), function () {
            $response = response('OK');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        });

        $this->assertFalse($response->headers->has('Pragma'));
        $this->assertFalse($response->headers->has('Expires'));
    }

    public function test_an_upstream_cache_control_header_is_replaced(): void
    {
        $response = (new NoCache)->handle(Request::create('/test'), function () {
            $response = response('OK');
            $response->setMaxAge(3600);
            $response->setPublic();

            return $response;
        });

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringNotContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_accepts_non_illuminate_responses(): void
    {
        // Regression: the return type used to be Response|JsonResponse|RedirectResponse,
        // so any Symfony response (downloads, streams) raised a TypeError.
        $response = (new NoCache)->handle(
            Request::create('/test'),
            fn () => new StreamedResponse(fn () => print 'chunk'),
        );

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_the_middleware_alias_is_registered(): void
    {
        $this->assertSame(
            NoCache::class,
            $this->app['router']->getMiddleware()['no.cache'] ?? null,
        );
    }
}
