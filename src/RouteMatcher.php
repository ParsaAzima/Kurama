<?php

declare(strict_types=1);

namespace Kurama;

use Kurama\Exceptions\MethodNotAllowedException;

/**
 * Matches normalized method/path pairs against a route collection.
 *
 * Expects an uppercase HTTP method and a normalized URI path.
 */
final class RouteMatcher
{
    /**
     * @return array{route: Route, parameters: array<string, string>}|null
     */
    public function match(RouteCollection $routes, string $method, string $path): ?array
    {
        $allowed = [];

        foreach ($routes->all() as $route) {
            $parameters = $route->matchPath($path);

            if ($parameters === null) {
                continue;
            }

            if ($route->supports($method)) {
                return ['route' => $route, 'parameters' => $parameters];
            }

            foreach ($route->methods() as $routeMethod) {
                $allowed[$routeMethod] = true;
            }
        }

        if ($allowed !== []) {
            if (isset($allowed['GET'])) {
                $allowed['HEAD'] = true;
            }
            throw new MethodNotAllowedException(array_keys($allowed), $method, $path);
        }

        return null;
    }
}
