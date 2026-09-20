# Laravel API Middleware
A Laravel package providing middleware for API enhancement, including trust proxies, no-cache enforcement, and SSL requirement validation.

[![Latest Stable Version](https://img.shields.io/packagist/v/aporat/laravel-api-middleware.svg?style=flat-square&logo=composer)](https://packagist.org/packages/aporat/laravel-api-middleware)
[![Monthly Downloads](https://img.shields.io/packagist/dm/aporat/laravel-api-middleware.svg?style=flat-square&logo=composer)](https://packagist.org/packages/aporat/laravel-api-middleware)
[![Codecov](https://img.shields.io/codecov/c/github/aporat/laravel-api-middleware?style=flat-square)](https://codecov.io/github/aporat/laravel-api-middleware)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-orange.svg?style=flat-square)](https://laravel.com/docs/13.x)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/aporat/laravel-api-middleware/ci.yml?style=flat-square)
[![License](https://img.shields.io/packagist/l/aporat/laravel-api-middleware.svg?style=flat-square)](https://github.com/aporat/laravel-api-middleware/blob/master/LICENSE)

A Laravel package offering middleware to enhance API security and performance with trust proxies, cache prevention, and SSL enforcement.

## Requirements
- **PHP**: 8.4, 8.5
- **Laravel**: 13.x

## Installation
Install the package via [Composer](https://getcomposer.org/):

```bash
composer require aporat/laravel-api-middleware
```

The service provider (`ApiMiddlewareServiceProvider`) is automatically registered via Laravel's package discovery. If auto-discovery is disabled, add it to `config/app.php`:

```php
'providers' => [
    // ...
    Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider::class,
],
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Aporat\Laravel\ApiMiddleware\ApiMiddlewareServiceProvider" --tag="api-middleware-config"
```

This copies `api-middleware.php` to your `config/` directory.

## Configuration

Edit `config/api-middleware.php` to customize the middleware settings:

```php
<?php

return [
    'trust_proxies' => [
        // IPs/CIDRs, a comma separated string, "REMOTE_ADDR", or "*" for any proxy.
        'proxies' => ['127.0.0.1', '10.0.0.0/8'],
        // A Request::HEADER_* bitmask, a named set, or a list of names.
        'headers' => 'x_forwarded_aws_elb',
    ],
    'no_cache' => [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
        'pragma' => 'no-cache',
        'expires' => '0',
    ],
    'ssl_required' => [
        // Path patterns for Request::is() — leading slash optional, "*" supported.
        'except_routes' => ['ping'],
        'except_environments' => ['local', 'development', 'testing'],
        'status' => 403,
    ],
];
```

- **`trust_proxies`**: Trusted proxy IPs/CIDRs and which `X-Forwarded-*` headers to honour.
  `headers` accepts a raw `Symfony\Component\HttpFoundation\Request::HEADER_*` bitmask, one of
  the named sets below, or a list of names that are OR'd together:
  `forwarded`, `x_forwarded_for`, `x_forwarded_host`, `x_forwarded_proto`, `x_forwarded_port`,
  `x_forwarded_prefix`, `x_forwarded_aws_elb` (the default), `x_forwarded_traefik`.
- **`no_cache`**: `Cache-Control`, `Pragma` and `Expires` values. Setting any of them to `null`
  omits the header and strips one set upstream. Symfony normalises `Cache-Control` — directives
  come back alphabetised with `private` appended unless `public`/`s-maxage` is present — so the
  emitted header will not match the configured string character for character.
- **`ssl_required`**: Exempt path patterns / environments, and the HTTP status used for rejections.

## Usage

### Middleware
Apply the middleware to routes using their aliases:

```php
// routes/api.php
Route::middleware(['trust.proxies', 'no.cache', 'ssl.required'])->get('/test', function () {
    return response()->json(['message' => 'API Enhanced!']);
});
```

- **`trust.proxies`**: Trusts the configured proxies so `X-Forwarded-*` headers are honoured
  when resolving the client IP, scheme and port.
- **`no.cache`**: Prevents caching of API responses.
- **`ssl.required`**: Rejects plain-HTTP requests with an `SslRequiredException` (a Symfony
  `HttpException`), except on exempt paths/environments.

### Ordering with Laravel's own TrustProxies

Laravel 13 always includes `Illuminate\Http\Middleware\TrustProxies` in the global stack, and it
**resets** the trusted set on every request. Register this package's `TrustProxies` as a
replacement rather than appending it, so it runs in the right slot instead of racing the built-in:

```php
// bootstrap/app.php
use Aporat\Laravel\ApiMiddleware\NoCache;
use Aporat\Laravel\ApiMiddleware\TrustProxies;

->withMiddleware(function (Middleware $middleware) {
    $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);
    $middleware->append(NoCache::class);
})
```

`ssl.required` must run *after* `trust.proxies`, otherwise every request behind a TLS-terminating
load balancer looks insecure.

### Error handling

`SslRequiredException` extends `Symfony\Component\HttpKernel\Exception\HttpException` and
deliberately does **not** define a `render()` method — Laravel gives an exception's own `render()`
precedence over the application's `Exceptions::render()` callbacks, so defining one would force the
package's error shape onto the host app. Format it however your app formats HTTP errors:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (HttpException $e) {
        return new JsonResponse([
            'error_code' => $e->getStatusCode(),
            'error_message' => $e->getMessage(),
        ], $e->getStatusCode());
    });
})
```

### Manual Instantiation

```php
use Aporat\Laravel\ApiMiddleware\NoCache;
use Aporat\Laravel\ApiMiddleware\SSLRequired;
use Aporat\Laravel\ApiMiddleware\TrustProxies;

$trustProxies = new TrustProxies(['192.168.1.1'], 'x_forwarded_for');
$noCache = new NoCache('no-cache, max-age=0', pragma: null);
$sslRequired = new SSLRequired(['health/*'], ['testing']);
```

Passing `null` for any constructor argument falls back to the corresponding config value.

## Testing
Run the package's unit tests:

```bash
vendor/bin/phpunit
```

With coverage:

```bash
vendor/bin/phpunit --coverage-text --coverage-clover coverage.xml --log-junit junit.xml
```

Requires Xdebug or PCOV for coverage reports.

## Contributing
Contributions are welcome! Please:
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m 'Add your feature'`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a pull request.

## License
This package is open-sourced under the [MIT License](https://opensource.org/licenses/MIT). See the [LICENSE](LICENSE) file for details.

## Support
- **Issues**: [github.com/aporat/laravel-api-middleware/issues](https://github.com/aporat/laravel-api-middleware/issues)
- **Source**: [github.com/aporat/laravel-api-middleware](https://github.com/aporat/laravel-api-middleware)
