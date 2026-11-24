<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use Kurama\Exceptions\RouteNotFoundException;

/**
 * Flask-inspired micro router.
 *
 * Routes are registered with get()/post()/... or a generic match() call,
 * then dispatched with an HTTP method and a URI path.
 */
final class Router
{
    public function __construct(
        private readonly RouteCollection $routes = new RouteCollection(),
        private readonly RouteMatcher $matcher = new RouteMatcher(),
    ) {
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->match('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->match('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->match('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->match('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->match('DELETE', $pattern, $handler);
    }

    public function options(string $pattern, mixed $handler): Route
    {
        return $this->match('OPTIONS', $pattern, $handler);
    }

    public function head(string $pattern, mixed $handler): Route
    {
        return $this->match('HEAD', $pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): Route
    {
        return $this->match('ANY', $pattern, $handler);
    }

    /**
     * Register a route for one or more HTTP methods.
     *
     * @param list<string>|string $methods
     */
    public function match(array|string $methods, string $pattern, mixed $handler): Route
    {
        $methods = is_string($methods) ? [$methods] : $methods;
        $normalized = [];

        foreach ($methods as $method) {
            if (!is_string($method) || trim($method) === '') {
                throw new InvalidArgumentException('HTTP method must be a non-empty string.');
            }
            $normalized[] = strtoupper(trim($method));
        }

        $route = new Route($normalized, $pattern, $handler);
        $this->routes->add($route);

        return $route;
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Find the first matching route and return its handler result.
     *
     * @throws RouteNotFoundException when no path matches
     * @throws \Kurama\Exceptions\MethodNotAllowedException when the path exists but the method is unsupported
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method must not be empty.');
        }

        $path = self::normalizePath($uri);
        $result = $this->matcher->match($this->routes, $method, $path);

        if ($result === null) {
            throw new RouteNotFoundException($method, $path);
        }

        return call_user_func_array($result['route']->handler(), array_values($result['parameters']));
    }

    /**
     * Drop query strings and fragments, enforce a leading slash and ignore
     * trailing slashes (except for the root path).
     */
    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }
}
