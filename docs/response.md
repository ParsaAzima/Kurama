# HTTP response

`Kurama\Response` is an immutable-style value object. `send()` is the only method that emits output.

## Factories

```php
use Kurama\Response;

Response::text('Hello');                 // text/plain
Response::html('<h1>Hello</h1>');        // text/html
Response::json(['ok' => true], 201);     // JsonResponse, application/json
Response::redirect('/home', 302);
Response::make(['ok' => true]);          // arrays become JsonResponse
Response::make('plain');                 // plain string response
```

`Response::json()` returns a `Kurama\JsonResponse`, which encodes with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`.

## Body

```php
$response = Response::text('Hello ');
$response->getBody()->write('world');
echo $response->getContent(); // Hello world
```

`Kurama\Body` has only `write(string): self` and `__toString()`. It is not a PSR-7 stream. `with*` methods return a cloned response that shares the same body instance, matching the familiar Slim pattern.

## Immutable modifiers

```php
$response = Response::text('hi')
    ->withStatus(201)
    ->withHeader('X-Test', 'yes')
    ->withHeaders(['X-Other' => 'value']);
```

| Method | Description |
| --- | --- |
| `withStatus(int): self` | Copy with a different status. |
| `withHeader(string, string): self` | Copy with one header replaced. |
| `withHeaders(array): self` | Copy with multiple headers merged. |

## Inspection and sending

| Method | Description |
| --- | --- |
| `getStatus(): int` | Status code. |
| `getHeaders(): array` | All headers. |
| `getHeader(string, ?string): ?string` | Case-insensitive header lookup. |
| `getContent(): string` | Current body as a string. |
| `getBody(): Body` | Writable body object. |
| `send(): void` | Sets `http_response_code()`, emits headers and echoes the body. |

`send()` skips header emission if headers have already been sent, but still echoes the body.

## Handler results

Inside `Application`, handlers may return:

- a `Response` (used as-is),
- an array, `JsonSerializable` or `stdClass` (converted to `JsonResponse`),
- any scalar or stringable value (converted to a text response),
- `null` (empty response).
