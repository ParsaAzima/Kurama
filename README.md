# Kurama

Kurama is a lightweight PHP micro framework inspired by Flask's developer experience. It is built in layers:

- `Router` — standalone, side-effect-free routing core (native PHP/PCRE, zero dependencies).
- `Application` — optional thin layer adding HTTP requests/responses, route groups, middleware and error rendering.

There is no DI container, no service provider system and no runtime dependency.

See [ROADMAP.md](ROADMAP.md) for planned versions and design boundaries.

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
composer serve
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

$app = new Application(['debug' => true]);

$app->middleware(function (Request $request, callable $next): Response {
    return $next($request)->withHeader('X-Powered-By', 'Kurama');
});

$app->get('/hello/{name}', function (string $name, Request $request): Response {
    return Response::json([
        'message' => "Hello, {$name}!",
        'ip' => $request->ip(),
    ]);
});

$app->group(['prefix' => '/api'], function (Application $app): void {
    $app->get('/ping', fn (): Response => Response::json(['pong' => true]));
});

$app->run();
```

Run it with `composer serve` and visit `http://127.0.0.1:8000/hello/world`.

A complete example is in `examples/application.php`. A router-only example is in `examples/basic.php`.

## Project structure

```
src/
├── Application.php        HTTP layer: request handling, middleware, errors
├── Router.php             Routing API and dispatch
├── Route.php              One route: methods, pattern, handler, middleware
├── RouteCollection.php    Ordered route storage
├── RouteMatcher.php       Matching and 404/405 decisions
├── Request.php            Incoming HTTP request value object
├── Response.php           HTTP response value object
├── JsonResponse.php       JSON-encoded response
└── Exceptions/            RouteNotFoundException, MethodNotAllowedException
public/index.php           Front controller
tests/                     PHPUnit tests
examples/                  Runnable examples
```

## HTTP methods

Available on both `Application` and `Router`:

```php
$app->get($pattern, $handler);
$app->post($pattern, $handler);
$app->put($pattern, $handler);
$app->patch($pattern, $handler);
$app->delete($pattern, $handler);
$app->options($pattern, $handler);
$app->head($pattern, $handler);
$app->any($pattern, $handler);
$app->match(['GET', 'POST'], $pattern, $handler);
```

`any()` accepts every supported method. HEAD requests implicitly fall back to a GET route unless an explicit HEAD route is registered earlier. OPTIONS must be registered explicitly.

## Route parameters

Placeholders match exactly one URL segment:

```php
$app->get('/users/{id}', $handler);
$app->get('/posts/{post}/comments/{comment}', $handler);
$app->get('/products/{slug}', $handler);
```

- Parameters are strings, passed to handlers in declaration order.
- Parameters never cross `/`.
- Percent-encoded values are decoded with `rawurldecode()`; `/products/a%2Fb` yields `a/b` as one parameter.
- Inside an `Application` handler, a parameter can also be requested by name, and `Request` can be injected by type or by a `$request` argument name.
- `$request->param('id')` exposes matched parameters inside middleware.

## HTTP request

`Kurama\Request` is immutable. `Request::capture()` builds it from superglobals; `Application::run()` does this automatically.

```php
$request->method();              // "GET"
$request->path();                // "/users/42"
$request->query('page', '1');    // query string
$request->input('name');         // body first, then query
$request->param('id');           // matched route parameter
$request->all();                 // query + params + body
$request->header('Accept');
$request->wantsJson();
$request->ip();
$request->withParams(['id' => '42']); // returns a copy
```

## HTTP response and JSON response

Handlers may return a `Response`, any value, or an array. `Application` converts non-responses automatically: arrays become `JsonResponse`, scalars become text responses.

```php
Response::text('Hello');
Response::html('<h1>Hello</h1>');
Response::json(['ok' => true], 201);
Response::redirect('/home', 302);
Response::make(['ok' => true]); // JsonResponse

$response->withStatus(201)->withHeader('X-Test', 'yes');
$response->getStatus();
$response->getHeader('Content-Type');
$response->getContent();
$response->send();
```

`with*` methods return copies; responses are not mutated in place. `JsonResponse` sets `Content-Type: application/json; charset=UTF-8` and encodes with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

## Route groups

Groups add a prefix and/or shared middleware. Nested groups are supported.

```php
$app->group(['prefix' => '/api', 'middleware' => [$authMiddleware]], function (Application $app): void {
    $app->get('/users/{id}', $handler);
});
// Registers GET /api/users/{id} with $authMiddleware.

$app->get('/admin', $handler)->middleware($adminMiddleware);
```

Middleware order is: global → group → route.

## Basic middleware

Middleware is any callable with the signature `fn (Request $request, callable $next): Response`.

```php
$app->middleware(function (Request $request, callable $next): Response {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader('X-Response-Time', (string) (microtime(true) - $start));
});

$app->get('/limited', $handler)->middleware(function (Request $request, callable $next): Response {
    return Response::text('blocked'); // short-circuit: $next is not called
});
```

There is no middleware interface, container or reflection magic — just callables.

## Error handling

`Application::handle()` converts errors into responses:

| Situation | Status | Response |
| --- | --- | --- |
| No matching path | 404 | JSON for APIs/XHR, HTML otherwise |
| Path matches but method does not | 405 | `Allow` header with allowed methods |
| Handler throws | 500 | Debug message if `debug` is true, generic message otherwise |

With `debug => false`, 500 errors are written to the PHP error log and details are hidden.

Customize rendering:

```php
$app->setErrorHandler(fn (Throwable $e, Request $request): Response =>
    Response::json(['error' => $e->getMessage()], 500));
```

The router also throws dedicated exceptions when used standalone:

```php
use Kurama\Exceptions\RouteNotFoundException;
use Kurama\Exceptions\MethodNotAllowedException;

// RouteNotFoundException: method(), path()
// MethodNotAllowedException: allowedMethods(), method(), path()
```

## URL normalization and matching rules

- Method names are trimmed and uppercased.
- Query strings and fragments are ignored: `/users/7?page=2` matches `/users/{id}`.
- A leading slash is enforced; trailing slashes are ignored (`/users`, `/users/` are the same).
- The first matching route wins; static routes registered before dynamic ones take precedence.
- `/user` never matches `/users`.
- Invalid patterns, unsupported methods and non-callable handlers throw `InvalidArgumentException` at registration time.

## API reference

### `Kurama\Application`

| Method | Description |
| --- | --- |
| `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any`, `match` | Register routes; returns `Route`. |
| `group(array $attributes, callable $callback)` | Prefix/middleware group; callback receives the `Application`. |
| `middleware(callable ...$middleware): self` | Add global middleware. |
| `setErrorHandler(callable): self` | Override error rendering. |
| `handle(Request $request): Response` | Dispatch without sending output. |
| `run(?Request $request = null): void` | Dispatch and send the response. |

### `Kurama\Router`

| Method | Description |
| --- | --- |
| `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any`, `match` | Register routes; returns `Route`. |
| `group(array $attributes, callable $callback)` | Prefix/middleware group; callback receives the `Router`. |
| `routes(): RouteCollection` | Inspect registered routes. |
| `resolve(string $method, string $uri): ?array` | Find a route without executing it. |
| `dispatch(string $method, string $uri): mixed` | Execute the handler and return its raw result. |
| `run(?string $method = null, ?string $uri = null): void` | Thin standalone HTTP entry point. |

### Other classes

- `Route`: `methods()`, `pattern()`, `handler()`, `parameterNames()`, `middleware(...)` / `middlewares()`, `supports()`, `matchPath()`.
- `RouteCollection`: `add()`, `all()`, `get()`, `count()`, `isEmpty()`.
- `RouteMatcher`: `match(RouteCollection, string, string): ?array`.
- `Request`: see HTTP request section.
- `Response` / `JsonResponse`: see HTTP response section.

## Running tests

```bash
composer install
vendor/bin/phpunit
```

## Current limitations

Not included (see [ROADMAP.md](ROADMAP.md)):

- Database/ORM, authentication, sessions or cookies.
- Template engines or view layers.
- Dependency injection containers, service providers or event buses.
- Named routes, inline regex constraints and route caching.
- Full CLI kernel or automatic OPTIONS responses.
