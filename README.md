# Kurama Router

Kurama is a lightweight, standalone PHP micro router inspired by Flask's routing API. It is intentionally small: a focused core with predictable behavior, native PHP/PCRE matching and no runtime dependencies.

This release is **Kurama Router v0.1.0** — HTTP routing only.

## Requirements

- PHP 8.3 or newer
- Composer

## Installation

```bash
composer require kurama/framework
```

For local development of this repository:

```bash
composer install
vendor/bin/phpunit
```

## Basic usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Kurama\Router;

$router = new Router();

$router->get('/', fn () => 'Hello from Kurama!');

$router->get('/users/{id}', function (string $id): string {
    return "User ID: {$id}";
});

echo $router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
```

`dispatch()` **returns** the handler's result. It never sends HTTP headers or output by itself, so the caller stays in control of the response.

A runnable version lives in `examples/basic.php`.

## Running the framework

Point your web server at `public/index.php`, or use PHP's built-in server:

```bash
composer serve
# http://127.0.0.1:8000/
# http://127.0.0.1:8000/users/42
```

The front controller registers routes and calls `run()`:

```php
$router = new Router();
$router->get('/users/{id}', fn (string $id) => ['id' => $id]);

$router->run();
```

`run()` reads `$_SERVER['REQUEST_METHOD']` and `$_SERVER['REQUEST_URI']` by default, dispatches the request, sends a `Content-Type` header, and echoes the result. You can pass the method and URI explicitly — useful for testing or custom entry points:

```php
$router->run('GET', '/users/42');
```

`run()` is a thin convenience adapter on top of `dispatch()`:

- It sets status `404` for a missing route and `405` (with an `Allow` header) for a method mismatch.
- Array, `JsonSerializable` and `stdClass` results are encoded as JSON; scalars are echoed as text.
- `dispatch()` remains side-effect free and still returns the handler result.

## HTTP methods

Registration helpers:

```php
$router->get($pattern, $handler);
$router->post($pattern, $handler);
$router->put($pattern, $handler);
$router->patch($pattern, $handler);
$router->delete($pattern, $handler);
$router->options($pattern, $handler);
$router->head($pattern, $handler);
$router->any($pattern, $handler);
```

`any()` registers a route that accepts `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS` and `HEAD`.

To register one handler for several methods:

```php
$router->match(['GET', 'POST'], '/users', $handler);
```

`match()` also accepts a single method string. Method names are normalized to uppercase. HEAD requests implicitly fall back to a matching GET route unless an explicit HEAD route is registered earlier for the same path. OPTIONS routes must be registered explicitly; there is no automatic OPTIONS response.

## Dynamic routes

Placeholders use curly braces and match exactly one URL segment:

```php
$router->get('/users/{id}', $handler);
$router->get('/posts/{post}/comments/{comment}', $handler);
$router->get('/products/{slug}', $handler);
```

- Parameters are extracted in declaration order and passed to the handler as positional arguments.
- Parameters are always strings.
- A parameter never crosses a `/` separator.
- Static segments are escaped before regex generation.
- Percent-encoded values are decoded with `rawurldecode()`, so `/products/a%2Fb` yields `a/b` as a single parameter.

Duplicate parameter names and placeholders that are not valid PHP/PHP-PCRE identifiers are rejected.

## URL normalization

- The method is trimmed and uppercased.
- Query strings and fragments are ignored: `/users/7?page=2` matches `/users/{id}`.
- A leading slash is always enforced.
- Trailing slashes are ignored: `/users`, `/users/` and `/users///` are the same path. The root path `/` stays `/`.
- Route patterns must begin with `/` and may not contain empty segments, whitespace, query strings or fragments.

## Error handling

Two dedicated exceptions are thrown; neither is coupled to HTTP response generation.

```php
use Kurama\Exceptions\RouteNotFoundException;
use Kurama\Exceptions\MethodNotAllowedException;

try {
    $router->dispatch('GET', '/missing');
} catch (RouteNotFoundException $exception) {
    // No registered path matched.
    $exception->method(); // "GET"
    $exception->path();   // "/missing"
}

try {
    $router->dispatch('POST', '/users');
} catch (MethodNotAllowedException $exception) {
    // The path exists, but no route accepts POST.
    $exception->allowedMethods(); // e.g. ["GET"]
    $exception->method();         // "POST"
    $exception->path();           // "/users"
}
```

Invalid route definitions (bad pattern, unsupported method, non-callable handler) throw `InvalidArgumentException` at registration time.

## Matching rules

- Routes are evaluated in registration order; the first matching route wins.
- A static route registered before a dynamic one wins for the same path.
- An exact path never matches a different path (`/user` does not match `/users`).
- If the path matches at least one route but none accept the method, `MethodNotAllowedException` is thrown with all allowed methods collected from those routes.

## API reference

### `Kurama\Router`

| Method | Description |
| --- | --- |
| `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any` | Register a route; returns the created `Route`. |
| `match(array\|string $methods, string $pattern, mixed $handler): Route` | Register a route for one or more methods. |
| `routes(): RouteCollection` | Inspect registered routes. |
| `dispatch(string $method, string $uri): mixed` | Match and execute; returns the handler result. |
| `run(?string $method = null, ?string $uri = null): void` | Dispatch and emit the result for a real HTTP request. |

### `Kurama\Route`

| Method | Description |
| --- | --- |
| `methods(): array` | Registered HTTP methods (`['ANY']` for any). |
| `pattern(): string` | Normalized route pattern. |
| `handler(): mixed` | Registered callable. |
| `parameterNames(): array` | Placeholder names in declaration order. |
| `supports(string $method): bool` | Method support check, including HEAD fallback. |
| `matchPath(string $path): ?array` | Decoded parameters, or `null` when the path does not match. |

### `Kurama\RouteCollection`

`add(Route $route)`, `all()`, `get(int $index)`, `count()`, `isEmpty()`. The collection implements `Countable` and never exposes its internal array by reference.

### `Kurama\RouteMatcher`

`match(RouteCollection $routes, string $method, string $path): ?array` returns `['route' => Route, 'parameters' => array]` or `null`, and throws `MethodNotAllowedException` for a path/method mismatch. Inputs are expected to be normalized by `Router::dispatch()`.

## Running tests

```bash
composer install
vendor/bin/phpunit
```

## Current limitations

This release deliberately does **not** include:

- Request or Response abstractions
- Middleware
- Dependency injection or a service container
- Application/kernel lifecycle, route groups or named routes
- Templates, database/ORM, sessions, cookies or authentication
- Route caching and automatic OPTIONS responses
- A full CLI kernel (`run()` is only a small HTTP entry point)

These may be considered in future releases.
