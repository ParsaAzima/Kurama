# HTTP request

`Kurama\Request` is an immutable value object. `Application::run()` builds one with `Request::capture()`; `Application::handle()` accepts one directly, which is useful for tests.

```php
use Kurama\Request;

$request = Request::capture();

// Or construct one explicitly:
$request = new Request(
    method: 'GET',
    uri: '/users/42?q=x',
    query: ['q' => 'x'],
    headers: ['Accept' => 'application/json'],
);
```

Constructor arguments: `method`, `uri`, `query`, `body`, `headers`, `server`, `cookies`, `files`, `params`.

## Method reference

| Method | Returns | Notes |
| --- | --- | --- |
| `method()` | `string` | Normalized uppercase method. |
| `getMethod()` | `string` | Slim-style alias. |
| `isMethod(string)` | `bool` | Case-insensitive check after trimming. |
| `path()` | `string` | Normalized URI path used by the router. |
| `header(string, mixed)` | `mixed` | Case-insensitive header lookup. |
| `getHeaderLine(string)` | `string` | Header value or empty string. |
| `query(string, mixed)` | `mixed` | Single query value. |
| `input(string, mixed)` | `mixed` | Body first, then query. |
| `param(string, mixed)` | `mixed` | Matched route parameter. |
| `getAttribute(string, mixed)` | `mixed` | Alias over route parameters. |
| `all()` | `array` | `query` + `params` + `body`. |
| `getQueryParams()` | `array` | All query values. |
| `getParsedBody()` | `array` | All parsed body values. |
| `ip()` | `string` | `REMOTE_ADDR` or `127.0.0.1`. |
| `wantsJson()` | `bool` | `Accept: application/json` or XHR. |
| `withParams(array)` | `Request` | Copy with new route parameters. |
| `withAttribute(string, mixed)` | `Request` | Copy with one added attribute. |

## Body parsing

`Request::capture()` parses the raw body:

- `Content-Type` containing `json` → `json_decode(..., true)`, invalid JSON becomes an empty array.
- `POST` → `$_POST`.
- `PUT` / `PATCH` / `DELETE` with `application/x-www-form-urlencoded` → parsed from `php://input`.
- Otherwise → empty array.

## URI details

`path()` applies `Router::normalizePath()`: query strings and fragments are dropped, a leading slash is enforced and trailing slashes are removed. Encoded values are left encoded so `%2F` does not become a separator; individual route parameters are decoded later.
