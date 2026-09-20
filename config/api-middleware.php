<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | `proxies` accepts IPs and CIDR ranges, a comma separated string, the
    | sentinel "REMOTE_ADDR" (trust whoever connected), or "*" to trust any
    | proxy. `headers` accepts a Request::HEADER_* bitmask, a named set such as
    | "x_forwarded_aws_elb", or a list of names that are OR'd together.
    |
    */

    'trust_proxies' => [
        'proxies' => ['127.0.0.1', '10.0.0.0/8'],
        'headers' => 'x_forwarded_aws_elb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Prevention
    |--------------------------------------------------------------------------
    |
    | Set any of these to null to omit that header (and strip it from responses
    | that set it upstream). Note that Symfony normalises `cache_control`: the
    | directives are alphabetised and `private` is appended unless `public` or
    | `s-maxage` is present, so the emitted header will not match this string
    | character for character.
    |
    */

    'no_cache' => [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
        'pragma' => 'no-cache',
        'expires' => '0',
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Enforcement
    |--------------------------------------------------------------------------
    |
    | `except_routes` are path patterns matched with Request::is() — the leading
    | slash is optional and "*" wildcards are supported (e.g. "health/*").
    |
    */

    'ssl_required' => [
        'except_routes' => ['ping'],
        'except_environments' => ['local', 'development', 'testing'],
        'status' => 403,
    ],

];
