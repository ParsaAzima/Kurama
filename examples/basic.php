<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\Router;

$router = new Router();

$router->get('/', function () {
    return 'Hello from Kurama!';
});

$router->get('/users', function () {
    return 'Users List';
});

$router->get('/users/{id}', function ($id) {
    return "User ID: {$id}";
});

$router->post('/users', function () {
    return 'User Created';
});

$router->put('/users/{id}', function ($id) {
    return "User {$id} Updated";
});

$router->patch('/users/{id}', function ($id) {
    return "User {$id} Patched";
});

$router->delete('/users/{id}', function ($id) {
    return "User {$id} Deleted";
});

echo $router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
);
