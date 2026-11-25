# Routing

## Registering routes

Both `Router` and `Application` expose the same registration methods:

```php
$app->get('/path', $handler);
$app->post('/path', $handler);
$app->put('/path', $handler);
$app->patch('/path', $handler);
$app->delete('/path', $handler);
$app->options('/path', $handler);
$app->head('/path', $handler);
$app->any('/path', $handler);
$app->match(['GET', 'POST'], '/path', $handler);
```

`match()` also accepts a single method string. Method names are trimmed and uppercased. Supported methods are `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD` and `ANY`.

`ANY` accepts every supported method. If combined with other methods, `ANY` wins and the route is stored as `['ANY']`.

### HEAD and OPTIONS

- A `HEAD` request falls back to an earlier matching `GET` route unless an explicit `HEAD` route matches first.
- An explicit `OPTIONS` route always wins. Otherwise, a path with at least one registered method automatically answers `OPTIONS` with **204** and an `Allow` header containing the registered methods plus `OPTIONS`. Disable with `options.auto => false`; a path with no routes still returns 404.
- The `Allow` header on a 405 response lists the methods registered for matching paths; implicit HEAD is not added.

## URI normalization

`Router::normalizePath()` is applied to dispatch URIs:

- Query strings and fragments are ignored: `/users/7?page=2#top` becomes `/users/7`.
- A leading slash is enforced.
- Trailing slashes are ignored: `/users`, `/users/` and `/users///` are the same path. Root stays `/`.

Route patterns are normalized the same way when the `Route` is constructed:

- Must begin with `/`.
- Cannot contain empty segments (`//`), whitespace, `?` or `#`.
- Trailing slashes are trimmed.

## Static and dynamic routes

```php
$app->get('/', $handler);
$app->get('/users', $handler);
$app->get('/users/{id}', $handler);
$app->get('/posts/{post}/comments/{comment}', $handler);
$app->get('/products/{slug}', $handler);
```

A placeholder matches exactly one URL segment. Parameters are strings and are passed to handlers in declaration order. Percent-encoded values are decoded with `rawurldecode()`, so `/products/a%2Fb` yields one parameter `a/b`.

A trailing `?` makes a parameter optional: `/users/{id?}` matches both `/users` and `/users/42`. Named constraint aliases (`int`, `slug`, `uuid`, `any`) can be used as `{id:int}` and extended with `Router::pattern('name', $regex)`.

Static routes are compared as plain strings before any regex is attempted.

## Constraints

Add a regex fragment after a colon:

```php
$app->get('/users/{id:\d+}', $handler);
$app->get('/posts/{year:\d{4}}/{slug:[a-z-]+}', $handler);
$app->get('/files/{name:[^/]+}', $handler);
```

Constraint safety rules:

- Must not match the empty string.
- Must not match `/` or `a/b` (so parameters cannot cross separators).
- Must not contain backreferences such as `\1`.
- Must not contain the regex delimiter `~`.
- One level of braces is supported for quantifiers such as `\d{4}`.

Static portions of the pattern are always escaped with `preg_quote()`. Invalid constraint definitions throw `InvalidArgumentException` at registration time.

## Named routes and URL generation

```php
$app->get('/users/{id:\d+}', $handler)->name('user.show');

$app->url('user.show', ['id' => 42]);    // /users/42
$app->urlFor('user.show', ['id' => 42]); // Slim-style alias
```

`Router::url()` / `Application::url()` throw `InvalidArgumentException` when:

- the name is not registered,
- a required parameter is missing,
- a value does not satisfy its constraint.

Values are percent-encoded with `rawurlencode()` after validation. `RouteCollection::findByName()` can inspect named routes. Route names may contain letters, digits, `_`, `.` and `-`, and must start with a letter or underscore.

## Route groups

```php
$app->group(['prefix' => '/api', 'middleware' => [$middleware]], function (Application $app): void {
    $app->get('/users/{id}', $handler);
    $app->post('/users', $handler);
});
```

The callback receives the same object you called `group()` on: `Application` for `Application::group()`, `Router` for `Router::group()`. Groups can be nested; prefixes concatenate and middleware accumulates in order.

Middleware execution order is: **global → outer group → inner group → route**.

## Matching rules

- Routes are evaluated in registration order; the first match wins.
- A static route registered before a dynamic route wins for the same path.
- An exact path never matches a different path (`/user` does not match `/users`).
- If the path matches at least one route but no route accepts the method, `MethodNotAllowedException` is thrown.
- If no path matches, `RouteNotFoundException` is thrown.

## Standalone dispatch

```php
$result = $router->dispatch('GET', '/users/42');         // executes handler
$match  = $router->resolve('GET', '/users/42');         // ['route' => ..., 'parameters' => ...]
$router->run('GET', '/users/42');                       // standalone HTTP adapter
```

`Router::run()` reads `$_SERVER` when arguments are omitted, sets status/headers and echoes the result. It is deliberately thin; use `Application` for the full HTTP layer.

## URL generation without route names

`url()` accepts three kinds of targets:

```php
$app->url('user.show', ['id' => 42]);                 // named route
$app->url('/users/{id:\d+}', ['id' => 42]);          // path pattern
$app->url(UserController::class . '@show', ['id' => 42]); // handler
$app->urlForHandler([UserController::class, 'show'], ['id' => 42]);
```

When a string starts with `/`, Kurama looks for a route with the same normalized pattern. A string containing `@` is matched against registered handlers. Missing targets or parameters throw `InvalidArgumentException`.

## Route caching

Enable production route caching in config:

```php
$app = AppFactory::create([
    'route.cache.enabled' => true,
    'route.cache.path' => __DIR__ . '/../storage/cache/routes.php',
    'route.cache.sources' => [__DIR__ . '/../config', __DIR__ . '/../routes'],
]);

$app->loadRoutes(function (Application $app): void {
    $app->get('/users/{id:\d+}', [UserController::class, 'show'])->name('user.show');
});
```

The first request registers the routes and writes a plain PHP file. Later requests load that file and skip the callback. `Application::routesFromCache()` and `cacheRoutes()` are available directly.

The cache records a signature of `route.cache.sources` (file mtimes and sizes). When any source changes, the cache is considered stale and `loadRoutes()` re-registers routes and rewrites the cache automatically. Use `composer console -- cache:clear` to delete it manually.

Cacheable handlers may be function names, `Class@method`, `[Class::class, 'method']`, invokable classes, or **closures with a resolver**. Anonymous classes still cannot be cached.

Provide `route.cache.closure_resolver` as a callable that maps a `Closure` to a string id when writing, and maps that id back to a callable when loading:

```php
$handlers = ['greet' => fn () => 'hello'];

$resolver = static function (mixed $value) use ($handlers) {
    return $value instanceof Closure ? 'greet' : ($handlers[$value] ?? null);
};

$app = AppFactory::create(['route.cache.closure_resolver' => $resolver]);
```

Without a resolver, closures throw `InvalidArgumentException`. Middleware follows the same rule.

The in-memory static dispatch index and last-request memo remain active after loading a cache, so caching does not reduce runtime throughput.
