<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware\Tests;

use Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ApiMiddlewareServiceProvider::class];
    }

    protected function tearDown(): void
    {
        // setTrustedProxies() is static global state; leaking it across tests
        // makes results order-dependent.
        SymfonyRequest::setTrustedProxies([], 0);

        parent::tearDown();
    }
}
