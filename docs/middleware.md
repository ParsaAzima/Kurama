# Middleware

Kurama middleware is just a callable. There is no interface, container or reflection magic.

## Signature

```php
function (Request $request, callable $next): Response
```

Call `$next($request)` to continue to the next middleware or the route handler. Return a `Response` either way.

## Adding middleware

```php
use Kurama\Request;
use Kurama\Response;

$app->add(function (Request $request, callable $next): Response {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader('X-Response-Time', (string) (microtime(true) - $start));
});

// Alias:
$app->middleware($middleware);
```

Route-level:

```php
$app->get('/admin', $handler)->add($adminMiddleware);
// or ->middleware($adminMiddleware)
```

Group-level:

```php
$app->group(['prefix' => '/api', 'middleware' => [$authMiddleware]], function (Application $app): void {
    $app->get('/me', $handler);
});
```

## Execution order

Pipeline construction order is global → group → route. The first added middleware is the outermost:

```php
$trace = [];

$app->add(function (Request $request, callable $next) use (&$trace): Response {
    $trace[] = 'global-before';
    $response = $next($request);
    $trace[] = 'global-after';

    return $response;
});

// Result: global-before, group-before, route-before,
//         route-after, group-after, global-after
```

## Short-circuiting

Do not call `$next()` to stop the pipeline:

```php
$app->add(function (Request $request, callable $next): Response {
    if ($request->getHeaderLine('X-Token') === '') {
        return Response::json(['error' => 'missing token'], 401);
    }

    return $next($request);
});
```

## Modifying the response

`Response::withHeader()` and `withStatus()` return copies:

```php
return $next($request)->withHeader('X-Powered-By', 'Kurama');
```

Route parameters are available in middleware via `$request->param('id')` or `$request->getAttribute('id')`. Use `$request->withAttribute()` to pass values inward.

## Notes

- Middleware may return any value; `Application` wraps non-responses with `Response::make()`.
- Exceptions thrown inside middleware are handled by the same error handler as route handlers.
- There is no automatic body parsing middleware; `Request` already parses JSON and form bodies at capture time.
