<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Exceptions\RouteNotFoundException;

/**
 * Flask-inspired micro router.
 *
 * Routes are registered with get()/post()/... or a generic match() call,
 * then dispatched with an HTTP method and a URI path.
 */
final class Router
{
    private string $groupPrefix = '';

    /** @var list<callable> */
    private array $groupMiddleware = [];

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

        $pattern = $this->groupPrefix === ''
            ? $pattern
            : '/' . $this->groupPrefix . '/' . ltrim($pattern, '/');

        $route = new Route($normalized, $pattern, $handler);
        if ($this->groupMiddleware !== []) {
            $route->middleware(...$this->groupMiddleware);
        }
        $this->routes->add($route);

        return $route;
    }

    /**
     * Register routes under a shared prefix and/or middleware.
     *
     * @param array{prefix?: string, middleware?: list<callable>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $prefix = trim((string) ($attributes['prefix'] ?? ''), '/');
        $this->groupPrefix = trim($this->groupPrefix . '/' . $prefix, '/');
        $this->groupMiddleware = array_merge($previousMiddleware, (array) ($attributes['middleware'] ?? []));

        try {
            $callback($this);
        } finally {
            $this->groupPrefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Convenience HTTP entry point: dispatch, encode and emit the result.
     *
     * This is the only method that touches superglobals and sends output.
     * Pass $method/$uri explicitly for testing or custom entry points;
     * null values are read from $_SERVER.
     */
    public function run(?string $method = null, ?string $uri = null): void
    {
        $method ??= (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri ??= (string) ($_SERVER['REQUEST_URI'] ?? '/');

        try {
            $result = $this->dispatch($method, $uri);
        } catch (RouteNotFoundException $exception) {
            http_response_code(404);
            echo $exception->getMessage();

            return;
        } catch (MethodNotAllowedException $exception) {
            http_response_code(405);
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $exception->allowedMethods()));
            }
            echo $exception->getMessage();

            return;
        } catch (InvalidArgumentException $exception) {
            http_response_code(500);
            echo $exception->getMessage();

            return;
        }

        if (!headers_sent()) {
            $isJson = is_array($result) || $result instanceof \JsonSerializable || $result instanceof \stdClass;
            header($isJson
                ? 'Content-Type: application/json; charset=UTF-8'
                : 'Content-Type: text/html; charset=UTF-8');
        }

        if (is_array($result) || $result instanceof \JsonSerializable || $result instanceof \stdClass) {
            echo json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );

            return;
        }

        echo is_scalar($result) || $result instanceof \Stringable ? (string) $result : '';
    }

    /**
     * Resolve a normalized request without executing its handler.
     *
     * @return array{route: Route, parameters: array<string, string>}|null
     */
    public function resolve(string $method, string $uri): ?array
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method must not be empty.');
        }

        return $this->matcher->match($this->routes, $method, self::normalizePath($uri));
    }

    /**
     * Find the first matching route and return its handler result.
     *
     * @throws RouteNotFoundException when no path matches
     * @throws MethodNotAllowedException when the path exists but the method is unsupported
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper(trim($method));
        $result = $this->resolve($method, $uri);

        if ($result === null) {
            throw new RouteNotFoundException($method, self::normalizePath($uri));
        }

        return call_user_func_array($result['route']->handler(), array_values($result['parameters']));
    }

    /**
     * Drop query strings and fragments, enforce a leading slash and ignore
     * trailing slashes (except for the root path).
     */
    public static function normalizePath(string $uri): string
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
