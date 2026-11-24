<?php

declare(strict_types=1);

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

// Let PHP's built-in server serve real files before routing.
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create(['debug' => true]);

$app->add(function (Request $request, callable $next): Response {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader('X-Response-Time', number_format((microtime(true) - $start) * 1000, 2) . 'ms');
});

$app->get('/', fn (): Response => $app->render('home', ['title' => 'Kurama']));

$app->group(['prefix' => '/api'], function (Application $app): void {
    $app->get('/ping', function (Request $request, Response $response): Response {
        return Response::json(['pong' => true]);
    });

    $app->get('/users/{id}', function (Request $request, Response $response, array $args): Response {
        return Response::json([
            'id' => $args['id'],
            'q' => $request->getQueryParams()['q'] ?? null,
        ]);
    });

    $app->post('/users', function (Request $request, Response $response): Response {
        return Response::json(['created' => true], 201);
    });
});

$app->run();
