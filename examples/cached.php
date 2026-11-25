<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Examples\CachedController;

$cachePath = sys_get_temp_dir() . '/kurama-routes.php';

$app = AppFactory::create([
    'debug' => true,
    'route.cache.enabled' => true,
    'route.cache.path' => $cachePath,
]);

// First run registers routes and writes the cache; later runs load it directly.
$app->loadRoutes(function (Application $app): void {
    $app->get('/users/{id:\d+}', [CachedController::class, 'show'])->name('user.show');
    $app->get('/health', [CachedController::class, 'health']);
});

echo $app->url('/users/{id:\d+}', ['id' => 42]), PHP_EOL;
echo $app->urlForHandler([CachedController::class, 'show'], ['id' => 7]), PHP_EOL;
$app->run();
