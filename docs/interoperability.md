# PSR-7 interoperability

Kurama provides a complete PSR-7 implementation in `Kurama\Http\*` and PSR-17 factories. The original lightweight `Kurama\Request` / `Kurama\Response` remain unchanged, so existing code keeps working.

## Dependencies

- `psr/http-message:^2.0`
- `psr/http-factory:^1.1`

## PSR-7 classes

| Class | Interface |
| --- | --- |
| `Kurama\Http\Uri` | `Psr\Http\Message\UriInterface` |
| `Kurama\Http\Stream` | `Psr\Http\Message\StreamInterface` |
| `Kurama\Http\Request` | `Psr\Http\Message\RequestInterface` |
| `Kurama\Http\ServerRequest` | `Psr\Http\Message\ServerRequestInterface` |
| `Kurama\Http\Response` | `Psr\Http\Message\ResponseInterface` |
| `Kurama\Http\UploadedFile` | `Psr\Http\Message\UploadedFileInterface` |

All `with*` methods return immutable clones. Headers are canonicalized to lowercase keys and exposed as arrays of values.

## PSR-17 factories

`UriFactory`, `StreamFactory`, `RequestFactory`, `ServerRequestFactory`, `ResponseFactory` and `UploadedFileFactory` live under `Kurama\Http\`.

```php
use Kurama\Http\ResponseFactory;

$factory = new ResponseFactory();
$response = $factory->createResponse(404)
    ->withHeader('X-Test', 'yes');
$response->getBody()->write('Not found');
```

## Using PSR-7 with Application

```php
use Kurama\AppFactory;
use Kurama\Http\ServerRequest;

$app = AppFactory::create();
$response = $app->handlePsr7(new ServerRequest('GET', '/users/42'));
echo $response->getStatusCode();
```

`handlePsr7()` converts the PSR request into Kurama's lightweight request, runs the normal pipeline and returns a PSR-7 response.

## Bridge

`Kurama\Bridge\PsrBridge` converts between both representations:

- `toKuramaRequest()`, `toPsrRequest()`
- `toKuramaResponse()`, `toPsrResponse()`
- `psr15Middleware()` when PSR-15 interfaces are installed

## PSR-15

Kurama does not require `psr/http-server-handler`. If you install it, `PsrBridge::psr15Middleware($middleware, $factory)` wraps a Kurama middleware as a PSR-15 middleware. Without those interfaces it throws a clear `InvalidArgumentException`.
