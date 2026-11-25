# Interoperability

Kurama does not depend on `psr/http-message` or `psr/http-server-handler`. It does provide a small duck-typed bridge for projects that already use PSR-7 style objects.

## PSR-7 bridge

```php
use Kurama\Bridge\PsrBridge;

$kuramaRequest = PsrBridge::toKuramaRequest($psrRequest);
$psrResponse = PsrBridge::toPsrResponse($kuramaResponse, $psr17ResponseFactory);
$kuramaResponse = PsrBridge::toKuramaResponse($psrResponse);
```

`PsrBridge::isPsrRequest($object)` checks for the expected getters before conversion. A PSR-17 response factory is any object with `createResponse(int $status)`.

## Handling a PSR request directly

```php
$psrResponse = $app->handlePsr($psrRequest, $responseFactory);
```

`handlePsr()` converts the request, runs the normal Kurama pipeline and converts the response back. It does not emit output.

## PSR-15 middleware

Kurama's middleware signature is `fn (Request $request, callable $next): Response`, not PSR-15. You can wrap a Kurama middleware for PSR-15 stacks with an adapter of your own:

```php
$kuramaMiddleware = function ($request, $next) { /* ... */ };
```

The bridge converts request/response objects; request handlers and middleware still use Kurama callables.

## Limits

- This is a converter, **not a PSR-7 implementation**.
- `$response->getBody()->write()` on a Kurama `Body` is not a stream; PSR conversion materializes the full string.
- Uploaded files and streamed responses are not fully represented.
- No dependency is installed for you; bring your own PSR-7 objects/factory.
