<?php

declare(strict_types=1);

namespace Kurama\Examples;

use Kurama\Application;
use Kurama\Request;
use Kurama\Response;
use Kurama\ServiceProvider;

/** Example provider: bind a service, add middleware and listen to an event. */
final class DemoServiceProvider extends ServiceProvider
{
    public function register(Application $app): void
    {
        $app->container->instance('demo.prefix', '[kurama]');
    }

    public function boot(Application $app): void
    {
        $app->middleware(function (Request $request, callable $next): Response {
            return $next($request)->withHeader('X-Demo-Provider', 'active');
        });

        $app->listen('response.ready', static function (Application $app, Response $response): void {
            $app->logger->debug('Response ready', ['status' => $response->getStatus()]);
        });
    }
}
