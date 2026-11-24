<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\Router;

$iterations = 100000;

$router = new Router();
$router->get('/', static fn (): string => 'ok');
$router->get('/users/{id:\d+}', static fn (string $id): string => $id);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $router->dispatch('GET', '/');
}
$staticTime = microtime(true) - $start;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $router->dispatch('GET', '/users/42');
}
$dynamicTime = microtime(true) - $start;

printf(
    "Static:  %d dispatches in %.4fs (%.0f req/s)\n",
    $iterations,
    $staticTime,
    $staticTime > 0 ? $iterations / $staticTime : 0,
);
printf(
    "Dynamic: %d dispatches in %.4fs (%.0f req/s)\n",
    $iterations,
    $dynamicTime,
    $dynamicTime > 0 ? $iterations / $dynamicTime : 0,
);

$large = new Router();
for ($i = 0; $i < 200; $i++) {
    $large->get('/route/' . $i, static fn (): string => 'ok');
}
$large->get('/target', static fn (): string => 'ok');

$iterations = 20000;
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $large->dispatch('GET', '/target');
}
$largeTime = microtime(true) - $start;

printf(
    "201 routes: %d dispatches in %.4fs (%.0f req/s)\n",
    $iterations,
    $largeTime,
    $largeTime > 0 ? $iterations / $largeTime : 0,
);
