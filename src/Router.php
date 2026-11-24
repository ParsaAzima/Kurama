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
    private const ALL_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    /** @var array<string, array<string, list<int>>> */
    private array $staticIndex = [];

    /** @var array<string, list<int>> */
    private array $dynamicIndex = [];

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

        $position = count($this->routes) - 1;
        foreach ($this->indexMethods($route->methods()) as $method) {
            if ($route->parameterNames() === []) {
                $this->staticIndex[$method][$route->pattern()][] = $position;
            } else {
                $this->dynamicIndex[$method][] = $position;
            }
        }

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

    public function named(string $name): ?Route
    {
        return $this->routes->findByName($name);
    }

    /** @param array<string, mixed> $params */
    public function url(string $name, array $params = []): string
    {
        $route = $this->named($name);
        if ($route === null) {
            throw new InvalidArgumentException("Route [{$name}] is not defined.");
        }

        return $route->uri($params);
    }

    /** Alias for Slim-style `urlFor()`. */
    public function urlFor(string $name, array $params = []): string
    {
        return $this->url($name, $params);
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

        $path = self::normalizePath($uri);

        // Fast path: exact static lookup, then only earlier dynamic routes.
        $staticPositions = $this->staticIndex[$method][$path] ?? [];
        $dynamicPositions = $this->dynamicIndex[$method] ?? [];
        $cutoff = $staticPositions === [] ? PHP_INT_MAX : $staticPositions[0];

        foreach ($dynamicPositions as $position) {
            if ($position >= $cutoff) {
                break;
            }
            $route = $this->routes->get($position);
            if ($route === null) {
                continue;
            }
            $parameters = $route->matchPath($path);
            if ($parameters !== null) {
                return ['route' => $route, 'parameters' => $parameters];
            }
        }

        foreach ($staticPositions as $position) {
            $route = $this->routes->get($position);
            if ($route !== null) {
                return ['route' => $route, 'parameters' => []];
            }
        }

        // Slow path is only reached when no method-compatible route matched:
        // collect allowed methods to distinguish 405 from 404.
        $allowed = [];
        foreach ($this->routes->all() as $route) {
            if ($route->matchPath($path) === null) {
                continue;
            }
            foreach ($route->methods() as $routeMethod) {
                $allowed[$routeMethod] = true;
            }
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_keys($allowed), $method, $path);
        }

        return null;
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
    /** @param list<string> $methods @return list<string> */
    private function indexMethods(array $methods): array
    {
        if ($methods === ['ANY']) {
            return self::ALL_METHODS;
        }

        if (in_array('GET', $methods, true)) {
            $methods[] = 'HEAD';
        }

        return array_values(array_unique($methods));
    }

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
