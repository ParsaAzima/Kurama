<?php

declare(strict_types=1);

use Kurama\Router;

// Let PHP's built-in server serve real files before routing.
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

$router = new Router();

$router->get('/', static fn (): string => 'Hello from Kurama!');
$router->get('/users', static fn (): array => ['users' => []]);
$router->get('/users/{id}', static fn (string $id): array => ['id' => $id]);
$router->post('/users', static fn (): array => ['created' => true]);
$router->put('/users/{id}', static fn (string $id): array => ['id' => $id, 'updated' => true]);
$router->patch('/users/{id}', static fn (string $id): array => ['id' => $id, 'patched' => true]);
$router->delete('/users/{id}', static fn (string $id): array => ['id' => $id, 'deleted' => true]);
$router->any('/health', static fn (): array => ['status' => 'ok']);

$router->run();
