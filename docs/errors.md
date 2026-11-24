# Error handling

## Router exceptions

When using `Router` directly, two dedicated exceptions are thrown:

```php
use Kurama\Exceptions\RouteNotFoundException;
use Kurama\Exceptions\MethodNotAllowedException;

try {
    $router->dispatch('GET', '/missing');
} catch (RouteNotFoundException $exception) {
    $exception->method(); // "GET"
    $exception->path();   // "/missing"
}

try {
    $router->dispatch('POST', '/users');
} catch (MethodNotAllowedException $exception) {
    $exception->allowedMethods(); // e.g. ["GET", "PUT"]
    $exception->allowed();        // alias
    $exception->method();         // "POST"
    $exception->path();           // "/users"
}
```

These exceptions are plain `RuntimeException` subclasses; they are not coupled to HTTP response generation.

Invalid route definitions throw `InvalidArgumentException` at registration time: bad pattern, empty method list, unsupported method, non-callable handler, invalid name or unsafe constraint.

## Application error rendering

`Application::handle()` catches every `Throwable` and renders a response:

| Situation | Status | Headers |
| --- | --- | --- |
| No path matched | 404 | — |
| Path matched, method not allowed | 405 | `Allow` with registered methods |
| Handler or middleware threw | 500 | — |

Response format:

- JSON when `Request::wantsJson()` is true or the path starts with `/api`.
- HTML otherwise.

With `config['debug'] === true`, exception messages are included. With `debug === false`, 500 errors are logged with `error_log()` and a generic status message is shown. 404 and 405 messages are always from the routing exceptions.

## Custom error handler

```php
$app->setErrorHandler(
    function (Throwable $exception, Request $request): Response {
        return Response::json([
            'error' => get_class($exception),
            'message' => $exception->getMessage(),
        ], 500);
    }
);
```

The callback receives the original exception and the request. It may return a `Response` or any value that `Response::make()` accepts. Once set, it replaces the default rendering completely, so handle all statuses you care about.

## Common failure cases

| Failure | Result |
| --- | --- |
| Controller with required constructor arguments | 500 with an explanation in debug mode. |
| Unresolvable handler argument | 500 with `Cannot resolve argument ...`. |
| Constraint mismatch during URL generation | `InvalidArgumentException`, not an HTTP response. |
| Missing named route | `InvalidArgumentException`, not an HTTP response. |
