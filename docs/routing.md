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
- `OPTIONS` routes must be registered explicitly. Kurama does not generate automatic OPTIONS responses.
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
