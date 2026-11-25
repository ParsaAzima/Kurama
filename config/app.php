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

    'view.engine' => null,
    'view.engines' => [],
    'view.cache.enabled' => false,
    'view.cache.path' => dirname(__DIR__) . '/storage/views',
    'view.cache.ttl' => 0,

    'session.name' => 'kurama_session',
    'session.native' => true,
    'log.path' => dirname(__DIR__) . '/storage/logs/app.log',
    'log.level' => 'debug',
    'log.max_bytes' => 0,

    'providers' => [],
    'discovery.enabled' => true,

    'route.cache.enabled' => false,
    'route.cache.path' => dirname(__DIR__) . '/storage/cache/routes.php',
    'route.cache.sources' => [dirname(__DIR__) . '/config', dirname(__DIR__) . '/routes'],

    'options.auto' => true,
    'events.defer' => false,
    'server.host' => '127.0.0.1',
    'server.port' => 8000,
    'server.workers' => 1,
];
