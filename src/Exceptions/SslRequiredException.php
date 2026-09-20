<?php

declare(strict_types=1);

namespace Aporat\Laravel\ApiMiddleware\Exceptions;

use Aporat\Laravel\ApiMiddleware\SSLRequired;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Thrown by {@see SSLRequired} for plain-HTTP requests.
 *
 * Deliberately has no `render()` method: an exception's own `render()` takes
 * precedence over the host application's `Exceptions::render()` callbacks, so
 * defining one here would force the package's error shape onto every consumer.
 * As a plain `HttpException` it is rendered by whatever the application already
 * does with HTTP errors.
 */
class SslRequiredException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message = 'SSL Required',
        int $status = 403,
        ?Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($status, $message, $previous, $headers);
    }
}
