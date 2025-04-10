<?php

return [
    'trust_proxies' => [
        'proxies' => ['127.0.0.1', '10.0.0.0/24', '10.0.0.0/8'],
        'headers' => \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_AWS_ELB,
    ],
    'no_cache' => [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
        'pragma' => 'no-cache',
    ],
    'ssl_required' => [
        'except_routes' => ['/ping'],
        'except_environments' => ['development', 'testing'],
    ],
];
