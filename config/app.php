<?php

declare(strict_types=1);

return [
    'app' => ['name' => 'Kurama'],
    'debug' => true,
    'timezone' => 'UTC',

    // Relative paths are resolved by the caller; the values below are the defaults.
    // 'views.path' => dirname(__DIR__) . '/views',
    // 'env.path' => dirname(__DIR__) . '/.env',
    'env.enabled' => true,

    'session.name' => 'kurama_session',
    'session.native' => true,
    'log.path' => dirname(__DIR__) . '/storage/logs/app.log',

    'providers' => [],
    'discovery.enabled' => true,

    'route.cache.enabled' => false,
    'route.cache.path' => dirname(__DIR__) . '/storage/cache/routes.php',
];
