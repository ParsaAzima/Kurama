<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

$app = AppFactory::create(['debug' => true]);

$app->add(function (Request $request, callable $next): Response {
    return $next($request)->withHeader('X-Powered-By', 'Kurama');
});

$app->get('/', function (Request $request, Response $response): Response {
    $response->getBody()->write('Hello from Kurama!');

    return $response;
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args): Response {
    $response->getBody()->write('Hello, ' . $args['name'] . '!');

    return $response;
});

$app->group(['prefix' => '/api'], function (Application $app): void {
    $app->get('/ping', function (Request $request, Response $response): Response {
        $response->getBody()->write('pong');

        return $response;
    });

    $app->get('/users/{id}', function (Request $request, Response $response, array $args): Response {
        return Response::json(['id' => $args['id'], 'q' => $request->getQueryParams()['q'] ?? null]);
    });
});

$app->run();
