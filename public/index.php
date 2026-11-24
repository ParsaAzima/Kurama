<?php

declare(strict_types=1);

use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

// Let PHP's built-in server serve real files before routing.
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

$app = new Application(['debug' => true]);

$app->middleware(static function (Request $request, callable $next): Response {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader(
        'X-Response-Time',
        number_format((microtime(true) - $start) * 1000, 2) . 'ms',
    );
});

$app->get('/', static fn (): Response => Response::html('<h1>Hello from Kurama!</h1>'));

$app->group(['prefix' => '/api'], static function (Application $app): void {
    $app->get('/ping', static fn (): Response => Response::json(['pong' => true]));

    $app->get('/users/{id}', static function (string $id, Request $request): Response {
        return Response::json(['id' => $id, 'q' => $request->query('q')]);
    });

    $app->post('/users', static fn (): Response => Response::json(['created' => true]));
});

$app->run();
