<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

$app = new Application(['debug' => true]);

$app->middleware(static function (Request $request, callable $next): Response {
    return $next($request)->withHeader('X-Powered-By', 'Kurama');
});

$app->get('/', static fn (): Response => Response::text('Hello from Kurama!'));

$app->get('/hello/{name}', static function (string $name, Request $request): Response {
    return Response::json([
        'message' => "Hello, {$name}!",
        'ip' => $request->ip(),
    ]);
});

$app->group(['prefix' => '/api'], static function (Application $app): void {
    $app->get('/ping', static fn (): Response => Response::json(['pong' => true]));
    $app->get('/users/{id}', static fn (string $id): Response => Response::json(['id' => $id]));
});

$app->run();
