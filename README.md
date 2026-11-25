 <p align="center">
  <img src="https://github.com/user-attachments/assets/525b3e78-06c7-43ec-8538-999e6c36904b" alt="Kurama Logo" width="420">
</p>

<h1 align="center">Kurama</h1>

<p align="center">
  A lightweight PHP micro framework inspired by Flask's developer experience.
</p>

<p align="center">
  <a href="#installation">Installation</a> ·
  <a href="docs/README.md">Documentation</a> ·
  <a href="ROADMAP.md">Roadmap</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Composer-Supported-885630?style=flat-square&logo=composer&logoColor=white" alt="Composer">
  <img src="https://img.shields.io/badge/License-MIT-white?style=flat-square" alt="MIT License">
</p>

---

Kurama is a lightweight PHP micro framework inspired by Flask's developer experience. It is built in layers:

- **Router** — standalone, side-effect-free routing core (native PHP/PCRE, zero dependencies).
- **Application** — optional thin layer adding HTTP requests/responses, route groups, middleware and error rendering.

There is no DI container, no service provider system and no runtime dependency.

Full documentation is in [docs/](docs/README.md). See [ROADMAP.md](ROADMAP.md) for planned versions and design boundaries.
## Requirements

- PHP 8.3 or newer
- Composer

## Installation

```bash
composer require kurama/framework:@dev
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

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Request;
use Kurama\Response;

$app = AppFactory::create(['debug' => true]);

$app->add(function (Request $request, callable $next): Response {
    return $next($request)->withHeader('X-Powered-By', 'Kurama');
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args): Response {
    $response->getBody()->write('Hello, ' . $args['name'] . '!');

    return $response;
});

$app->group(['prefix' => '/api'], function (Application $app): void {
    $app->get('/ping', fn (): Response => Response::json(['pong' => true]));
});

$app->run();
```

The observable style is Slim-like: `AppFactory::create()`, handlers receiving `($request, $response, $args)`, `$response->getBody()->write()`, `$app->add()` and `$app->run()`. It is **not** PSR-7 compliant and adds no runtime dependency — the body and messages are deliberately tiny local value objects.

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
├── Template.php           Native PHP template engine
├── Controller.php         Optional base controller
├── AppFactory.php         Slim-style factory
└── Exceptions/            RouteNotFoundException, MethodNotAllowedException
public/index.php           Front controller
views/                     Native PHP templates
tests/                     PHPUnit tests
examples/                  Runnable examples
docs/                      Complete documentation
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

`any()` accepts every supported method. HEAD requests implicitly fall back to a GET route unless an explicit HEAD route is registered earlier. An explicit OPTIONS route wins; otherwise a matching path automatically answers OPTIONS with 204 and an `Allow` header (`options.auto`, default true).

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

Constraints can be added inline:

```php
$app->get('/users/{id:\d+}', $handler);
$app->get('/posts/{year:\d{4}}/{slug:[a-z-]+}', $handler);
```

Constraints are regex fragments. For safety they must not match `/` or the empty string, and they cannot contain backreferences or the regex delimiter `~`. Static segments are still escaped with `preg_quote()`.

## Named routes

```php
$app->get('/users/{id:\d+}', $handler)->name('user.show');

$app->url('user.show', ['id' => 42]);    // /users/42
$app->urlFor('user.show', ['id' => 42]); // Slim-style alias
```

`url()` also works without a name: pass a path pattern (`/users/{id:\d+}`) or a handler (`UserController::class . '@show'`). `urlForHandler()` is available for callable handlers.

For production, enable route caching so route definitions are compiled once:

```php
$app = AppFactory::create([
    'route.cache.enabled' => true,
    'route.cache.path' => __DIR__ . '/../storage/cache/routes.php',
]);

$app->loadRoutes(function (Application $app): void {
    $app->get('/users/{id:\d+}', [UserController::class, 'show']);
});
```

Closure handlers cannot be cached; use class methods or function names. See [docs/routing.md](docs/routing.md).

## HTTP request

`Kurama\Request` is immutable. `Request::capture()` builds it from superglobals; `Application::run()` does this automatically.

```php
$request->method();              // "GET"
$request->getMethod();           // Slim-style alias
$request->path();                // "/users/42"
$request->getQueryParams();      // all query values
$request->getParsedBody();       // parsed request body
$request->getAttribute('id');    // matched route parameter
$request->withAttribute('user', $user); // returns a copy
$request->getHeaderLine('Accept');
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

$response->getBody()->write('Hello'); // Slim-style body writing
$response->withStatus(201)->withHeader('X-Test', 'yes');
$response->getStatus();
$response->getHeader('Content-Type');
$response->getContent();
$response->send();
```

`with*` methods return copies; responses are not mutated in place. `getBody()` returns a tiny `Kurama\Body` with `write()` and `__toString()` — enough for Slim-style handlers, but not a PSR-7 stream. `JsonResponse` sets `Content-Type: application/json; charset=UTF-8` and encodes with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

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

## Controllers

Extend `Kurama\Controller` to group related actions. Kurama resolves controller handlers without a container and injects the current request/response context.

```php
use Kurama\Controller;
use Kurama\Response;

final class UserController extends Controller
{
    public function show(string $id): Response
    {
        return $this->json([
            'id' => $this->param('id'),
            'method' => $this->request()->method(),
        ]);
    }
}

$app->get('/users/{id}', [UserController::class, 'show']);
$app->get('/legacy', UserController::class . '@showLegacy');
```

Inside a controller you get `request()`, `response()`, `param()`, `json()`, `text()`, `html()` and `redirect()`. Controllers must have a no-argument constructor; otherwise pass an already-created instance as the handler. The Slim-style `($request, $response, $args)` signature also works on controller methods.

## Utilities

Phase 4 adds five small utilities, all available on `Application` and in the container:

```php
$validated = $app->validate($_POST, ['email' => 'required|email']);

$app->session->set('user', 42);
$token = $app->csrf->token();
$app->middleware($app->csrf->middleware());

return Response::text('ok')->withCookie(new Kurama\Cookie('theme', 'dark'));

$app->logger->info('User registered', ['id' => 42]);
```

Validation failures return **422** with grouped `errors`, CSRF failures return **419**. See [docs/validation.md](docs/validation.md), [docs/session.md](docs/session.md), [docs/csrf.md](docs/csrf.md), [docs/cookie.md](docs/cookie.md) and [docs/logging.md](docs/logging.md).

## Providers and events

```php
use Kurama\Application;
use Kurama\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(Application $app): void
    {
        $app->container->singleton(MyService::class);
    }

    public function boot(Application $app): void
    {
        $app->middleware($app->csrf->middleware());
        $app->listen('response.ready', fn (Application $app, Response $response) => /* ... */);
    }
}

$app->register(AppServiceProvider::class);
```

Providers can also be listed in `config/app.php` under `providers`, or auto-discovered from `extra.kurama.providers` in any Composer package (`discovery.enabled`, default `true`). `boot()` runs once; the router core remains unchanged.

See [docs/service-providers.md](docs/service-providers.md) and [docs/events.md](docs/events.md).

## CLI

```bash
composer console -- list
composer console -- route:list
composer console -- help cache:routes
composer console -- version
```

Custom commands extend `Kurama\Console\Command` and are registered with `$app->console()->command(new MyCommand())`. `runConsole($argv)` returns an exit code; see [docs/cli.md](docs/cli.md).

## Templates

Kurama ships with a tiny native-PHP template engine — no Twig/Blade dependency.

```php
$app = AppFactory::create(['views.path' => __DIR__ . '/../views']);

$app->get('/', fn (): Response => $app->render('home', ['title' => 'Kurama']));
```

Inside a controller: `$this->view('home', ['title' => 'Kurama'])`.

Templates are plain PHP files; dot notation maps to subdirectories (`partials.greeting` → `views/partials/greeting.php`). Inside a template, `$this` is the `Template` instance:

```php
<h1><?= $this->escape($title) ?></h1>
<?= $this->render('partials.greeting', ['name' => 'world']) ?>
```

See [docs/templates.md](docs/templates.md) for the full API and safety rules.

## Configuration, environment and DI

```php
$app = AppFactory::create([
    'debug' => false,
    'views.path' => __DIR__ . '/../views',
    'env.path' => __DIR__ . '/../.env',
]);

$name = $app->config('app.name', 'Kurama');
$token = $app->env('API_TOKEN');
$service = $app->container->make(UserService::class);

$app->container->singleton(Cache::class);
```

- `Config` uses dot notation and loads `config/app.php`.
- `Environment` loads `.env` into `$_ENV`, `$_SERVER` and `getenv()` without overwriting existing values.
- `Container` supports auto-wiring, bindings, singletons and factories — no service providers.
- Controllers receive constructor dependencies from the container.

See [docs/configuration.md](docs/configuration.md), [docs/environment.md](docs/environment.md) and [docs/dependency-injection.md](docs/dependency-injection.md).

## Basic middleware

Middleware is any callable with the signature `fn (Request $request, callable $next): Response`.

```php
$app->add(function (Request $request, callable $next): Response {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader('X-Response-Time', (string) (microtime(true) - $start));
});

$app->get('/limited', $handler)->add(function (Request $request, callable $next): Response {
    return Response::text('blocked'); // short-circuit: $next is not called
});
```

`middleware()` and `Route::middleware()` remain available as aliases. There is no middleware interface, container or reflection magic — just callables.

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
| `url()` / `urlFor()` | Build a URL from a named route. |
| `handle(Request $request): Response` | Dispatch without sending output. |
| `run(?Request $request = null): void` | Dispatch and send the response. |

### `Kurama\Router`

| Method | Description |
| --- | --- |
| `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any`, `match` | Register routes; returns `Route`. |
| `group(array $attributes, callable $callback)` | Prefix/middleware group; callback receives the `Router`. |
| `routes(): RouteCollection` | Inspect registered routes. |
| `named()` / `url()` / `urlFor()` | Find or build URLs for named routes. |
| `resolve(string $method, string $uri): ?array` | Find a route without executing it. |
| `dispatch(string $method, string $uri): mixed` | Execute the handler and return its raw result. |
| `run(?string $method = null, ?string $uri = null): void` | Thin standalone HTTP entry point. |

### Other classes

- `AppFactory::create(array|Config $config = [], ?string $basePath = null, ?Container $container = null): Application`.
- `Controller`: `setContext()`, `request()`, `response()`, `param()`, `json()`, `text()`, `html()`, `redirect()`, `view()`, `make()`.
- `Container`, `Config`, `Environment`, `Template`: see [docs/api-reference.md](docs/api-reference.md).
- `Body`: `write(string): self`, `__toString()` — returned by `Response::getBody()`.
- `Route`: `methods()`, `pattern()`, `handler()`, `parameterNames()`, `constraints()`, `name()` / `getName()`, `uri()`, `middleware(...)` / `add(...)` / `middlewares()`, `supports()`, `matchPath()`.
- `RouteCollection`: `add()`, `all()`, `get()`, `count()`, `isEmpty()`, `findByName()`.
- `RouteMatcher`: `match(RouteCollection, string, string): ?array`.
- `Request`: see HTTP request section.
- `Response` / `JsonResponse`: see HTTP response section.

## Performance notes

- Route regexes are compiled once, at registration time.
- Exact static routes use a method + path hash index — O(1), independent of route count.
- Repeated identical dispatches hit a last-request memo; hot URLs avoid normalization and lookup entirely.
- Dynamic candidates are limited by the first matching static route, preserving registration order.
- Paths with a different segment count are rejected before PCRE runs.
- The container caches argument and constructor plans; request paths are normalized once.
- Providers boot once; event dispatch is a no-op when no listeners are registered.
- `examples/benchmark.php` runs static/dynamic and 201-route dispatch loops. See [docs/performance.md](docs/performance.md) for a comparison with FastRoute, Slim 4 and Laravel 11.

## Running tests

```bash
composer install
vendor/bin/phpunit
```

## Current limitations

Not included (see [ROADMAP.md](ROADMAP.md)):

- Database/ORM or authentication (session, cookie, CSRF and logging utilities are included).
- Third-party template engines and template caching (the built-in native-PHP engine with layouts is included).
- Container tags, compiler passes and async queued events. Providers, composer auto-discovery and a synchronous event bus are included.
- Threaded/async request handling and long-running worker servers.
- PSR-7 / PSR-15 interoperability (the style is inspired by Slim, not compatible with its contracts).
