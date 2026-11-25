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

    /** @var array<string, array<string, list<array{0: int, 1: Route}>>> */
    private array $staticIndex = [];

    /** @var array<string, list<array{0: int, 1: Route}>> */
    private array $dynamicIndex = [];

    /** Space-for-time cache: "METHOD /path" => [Route, handler]. */
    private array $staticDispatch = [];

    private bool $compiled = false;

    /** Last-request memoization: repeated identical dispatches skip normalization entirely. */
    private ?array $lastMatch = null;

    private string $lastMatchMethod = '';

    private string $lastMatchUri = '';

    private mixed $lastHandler = null;

    private string $lastHandlerMethod = '';

    private string $lastHandlerUri = '';

    private string $groupPrefix = '';

    /** @var list<callable> */
    private array $groupMiddleware = [];

    /** @var array<string, string> Named constraint aliases. */
    private array $patterns = [
        'int' => '\d+',
        'slug' => '[a-z0-9-]+',
        'uuid' => '[0-9a-f-]{36}',
        'any' => '[^/]+',
    ];

    public function __construct(
        private readonly RouteCollection $routes = new RouteCollection(),
        private readonly RouteMatcher $matcher = new RouteMatcher(),
    ) {
    }

    public function pattern(string $name, string $regex): self
    {
        $this->patterns[$name] = $regex;

        return $this;
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

        $pattern = $this->expandPatterns($pattern);
        $pattern = $this->groupPrefix === ''
            ? $pattern
            : '/' . $this->groupPrefix . '/' . ltrim($pattern, '/');

        $this->staticDispatch = [];
        $this->compiled = false;
        $this->lastMatch = null;
        $this->lastHandler = null;

        $route = new Route($normalized, $pattern, $handler);
        if ($this->groupMiddleware !== []) {
            $route->middleware(...$this->groupMiddleware);
        }
        $this->routes->add($route);

        $position = count($this->routes) - 1;
        $entry = [$position, $route];
        foreach ($this->indexMethods($route->methods()) as $method) {
            if ($route->parameterNames() === []) {
                $this->staticIndex[$method][$route->pattern()][] = $entry;
            } else {
                $this->dynamicIndex[$method][] = $entry;
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

    /** Clear per-request memoization between worker iterations. */
    public function forgetMemo(): void
    {
        $this->lastMatch = null;
        $this->lastHandler = null;
    }

    public function named(string $name): ?Route
    {
        return $this->routes->findByName($name);
    }

    /**
     * Build a URL from a route name, a path pattern or a Class@method handler.
     *
     * @param array<string, mixed> $params
     */
    public function url(string $target, array $params = []): string
    {
        $route = $this->named($target)
            ?? (str_starts_with($target, '/') ? $this->routeForPattern($target) : null)
            ?? (str_contains($target, '@') ? $this->routeForHandlerString($target) : null);

        if ($route === null) {
            throw new InvalidArgumentException("Route [{$target}] is not defined.");
        }

        return $route->uri($params);
    }

    /** Alias for Slim-style `urlFor()`. */
    public function urlFor(string $target, array $params = []): string
    {
        return $this->url($target, $params);
    }

    /** @param array<string, mixed> $params */
    public function urlForHandler(mixed $handler, array $params = []): string
    {
        $route = $this->routeForHandler($handler);
        if ($route === null) {
            throw new InvalidArgumentException('No route is registered for that handler.');
        }

        return $route->uri($params);
    }

    private function routeForPattern(string $pattern): ?Route
    {
        $pattern = $this->expandPatterns($pattern);
        // A '?' inside {name?} is part of the pattern, not a query string.
        $normalized = str_contains($pattern, '{')
            ? '/' . trim($pattern, '/')
            : self::normalizePath($pattern);
        if ($normalized === '') {
            $normalized = '/';
        }
        foreach ($this->routes->all() as $route) {
            if ($route->pattern() === $normalized) {
                return $route;
            }
        }

        return null;
    }

    private function routeForHandlerString(string $handler): ?Route
    {
        [$class, $method] = explode('@', $handler, 2);

        return $this->routeForHandler([$class, $method]);
    }

    private function routeForHandler(mixed $handler): ?Route
    {
        foreach ($this->routes->all() as $route) {
            if ($this->handlerMatches($route->handler(), $handler)) {
                return $route;
            }
        }

        return null;
    }

    private function handlerMatches(mixed $actual, mixed $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }

        if (is_string($expected) && str_contains($expected, '@')) {
            $expected = explode('@', $expected, 2);
        }

        if (is_array($actual) && is_array($expected) && isset($actual[0], $actual[1], $expected[0], $expected[1])) {
            $actualClass = is_object($actual[0]) ? get_class($actual[0]) : (string) $actual[0];
            $expectedClass = is_object($expected[0]) ? get_class($expected[0]) : (string) $expected[0];

            return $actualClass === $expectedClass
                && strcasecmp((string) $actual[1], (string) $expected[1]) === 0;
        }

        return is_object($actual) && is_object($expected) && get_class($actual) === get_class($expected);
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
            $allowed = $exception->allowedMethods();
            if (!in_array('OPTIONS', $allowed, true)) {
                $allowed[] = 'OPTIONS';
            }
            if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            $isOptions = strtoupper(trim($method ?? (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))) === 'OPTIONS';
            http_response_code($isOptions ? 204 : 405);
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $allowed));
            }
            if (!$isOptions) {
                echo $exception->getMessage();
            }

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
     * Resolve a request without executing its handler.
     *
     * @return array{route: Route, parameters: array<string, string>}|null
     */
    public function resolve(string $method, string $uri): ?array
    {
        if (
            $this->lastMatch !== null
            && $method === $this->lastMatchMethod
            && $uri === $this->lastMatchUri
        ) {
            return $this->lastMatch;
        }

        $this->lastMatchMethod = $method;
        $this->lastMatchUri = $uri;
        $method = self::normalizeMethod($method);
        $path = self::normalizePath($uri);
        $this->compile();

        $key = $method . ' ' . $path;
        if (isset($this->staticDispatch[$key])) {
            return $this->lastMatch = [
                'route' => $this->staticDispatch[$key][0],
                'parameters' => [],
            ];
        }

        return $this->lastMatch = $this->resolveWithoutCache($method, $path);
    }

    /**
     * Find the first matching route and return its handler result.
     *
     * @throws RouteNotFoundException when no path matches
     * @throws MethodNotAllowedException when the path exists but the method is unsupported
     */
    public function dispatch(string $method, string $uri): mixed
    {
        if (
            $this->lastHandler !== null
            && $method === $this->lastHandlerMethod
            && $uri === $this->lastHandlerUri
        ) {
            return ($this->lastHandler)();
        }

        $this->lastHandlerMethod = $method;
        $this->lastHandlerUri = $uri;

        $method = self::normalizeMethod($method);
        $path = self::normalizePath($uri);
        $this->compile();

        $key = $method . ' ' . $path;
        if (isset($this->staticDispatch[$key])) {
            return ($this->lastHandler = $this->staticDispatch[$key][1])();
        }

        $result = $this->resolveWithoutCache($method, $path);
        if ($result === null) {
            throw new RouteNotFoundException($method, $path);
        }

        // Dynamic handlers need route parameters, so they cannot use the
        // zero-argument memoized fast path.
        $this->lastHandler = null;

        return call_user_func_array($result['route']->handler(), array_values($result['parameters']));
    }

    /**
     * Precompute a flat lookup table for every static route.
     * This trades a small amount of memory for a single hash lookup per request.
     */
    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }

        foreach ($this->routes->all() as $route) {
            if ($route->parameterNames() !== []) {
                continue;
            }

            foreach ($this->indexMethods($route->methods()) as $method) {
                $key = $method . ' ' . $route->pattern();
                $match = $this->resolveWithoutCache($method, $route->pattern());
                if ($match !== null && $match['route'] === $route) {
                    $this->staticDispatch[$key] = [$route, $route->handler()];
                }
            }
        }

        $this->compiled = true;
    }

    /** @return array{route: Route, parameters: array<string, string>}|null */
    private function resolveWithoutCache(string $method, string $path): ?array
    {
        $staticEntries = $this->staticIndex[$method][$path] ?? [];
        $dynamicEntries = $this->dynamicIndex[$method] ?? [];
        $cutoff = $staticEntries === [] ? PHP_INT_MAX : $staticEntries[0][0];

        foreach ($dynamicEntries as [$position, $route]) {
            if ($position >= $cutoff) {
                break;
            }
            $parameters = $route->matchPath($path);
            if ($parameters !== null) {
                return ['route' => $route, 'parameters' => $parameters];
            }
        }

        foreach ($staticEntries as [, $route]) {
            return ['route' => $route, 'parameters' => []];
        }

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
            if (isset($allowed['GET'])) {
                $allowed['HEAD'] = true;
            }
            throw new MethodNotAllowedException(array_keys($allowed), $method, $path);
        }

        return null;
    }

    private static function normalizeMethod(string $method): string
    {
        if ($method !== '' && ctype_upper($method)) {
            return $method;
        }

        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method must not be empty.');
        }

        return $method;
    }

    /**
     * Drop query strings and fragments, enforce a leading slash and ignore
     * trailing slashes (except for the root path).
     */
    private function expandPatterns(string $pattern): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*):([A-Za-z_][A-Za-z0-9_]*)\}/',
            function (array $match): string {
                $regex = $this->patterns[$match[2]] ?? null;

                return $regex === null ? $match[0] : '{' . $match[1] . ':' . $regex . '}';
            },
            $pattern,
        );
    }

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
        // Avoid parse_url() for the overwhelmingly common clean path case.
        if (
            $uri !== ''
            && $uri[0] === '/'
            && strpbrk($uri, '?#') === false
            && ($uri === '/' || $uri[strlen($uri) - 1] !== '/')
        ) {
            return $uri;
        }

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
