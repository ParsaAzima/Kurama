# API reference

All classes live under the `Kurama` namespace. Exceptions live under `Kurama\Exceptions`.

## AppFactory

| Method | Description |
| --- | --- |
| `static create(array $config = []): Application` | Create an application. Config keys: `debug`, `timezone`. |

## Application

| Method | Description |
| --- | --- |
| `__construct(array $config = [], ?Router $router = null)` | Create with config and optional router instance. |
| `get/post/put/patch/delete/options/head/any(string $pattern, mixed $handler): Route` | Register a route. |
| `match(array\|string $methods, string $pattern, mixed $handler): Route` | Register for one or more methods. |
| `group(array $attributes, callable $callback): void` | Prefix/middleware group; callback receives `Application`. |
| `middleware(callable ...$middleware): self` | Add global middleware. |
| `add(callable ...$middleware): self` | Alias of `middleware()`. |
| `url(string $name, array $params = []): string` | Build URL from a named route. |
| `urlFor(string $name, array $params = []): string` | Alias of `url()`. |
| `setErrorHandler(callable $handler): self` | Set custom error renderer. |
| `handle(Request $request): Response` | Dispatch without sending output. |
| `run(?Request $request = null): void` | Dispatch and send response. |
| `router` (public readonly `Router`) | Underlying router. |

## Router

| Method | Description |
| --- | --- |
| `__construct(?RouteCollection $routes = null, ?RouteMatcher $matcher = null)` | Defaults are created automatically. |
| `get/post/put/patch/delete/options/head/any(string $pattern, mixed $handler): Route` | Register a route. |
| `match(array\|string $methods, string $pattern, mixed $handler): Route` | Register for one or more methods. |
| `group(array $attributes, callable $callback): void` | Prefix/middleware group; callback receives `Router`. |
| `routes(): RouteCollection` | Registered routes. |
| `named(string $name): ?Route` | Find a named route. |
| `url(string $name, array $params = []): string` | Build URL from a named route. |
| `urlFor(string $name, array $params = []): string` | Alias of `url()`. |
| `resolve(string $method, string $uri): ?array` | `['route' => Route, 'parameters' => array]` or `null`. |
| `dispatch(string $method, string $uri): mixed` | Execute handler, return raw result. |
| `run(?string $method = null, ?string $uri = null): void` | Standalone HTTP adapter. |
| `static normalizePath(string $uri): string` | Shared path normalization. |

## Route

| Method | Description |
| --- | --- |
| `__construct(array $methods, string $pattern, mixed $handler)` | Validate and compile a route. |
| `methods(): array` | Registered HTTP methods. |
| `pattern(): string` | Normalized pattern with constraints. |
| `handler(): mixed` | Registered handler. |
| `parameterNames(): array` | Placeholder names in order. |
| `constraints(): array` | Map of parameter name to constraint expression. |
| `name(string $name): self` | Assign a route name. |
| `getName(): ?string` | Route name. |
| `uri(array $params = []): string` | Build a concrete URI with validation and encoding. |
| `middleware(callable ...$middleware): self` | Attach route middleware. |
| `add(callable ...$middleware): self` | Alias of `middleware()`. |
| `middlewares(): array` | Attached middleware. |
| `supports(string $method): bool` | Method support including ANY and HEAD fallback. |
| `matchPath(string $path): ?array` | Decoded parameters or `null`. |

## RouteCollection

Implements `Countable`.

| Method | Description |
| --- | --- |
| `add(Route $route): void` | Append a route. |
| `all(): array` | Copy of the ordered route list. |
| `get(int $index): ?Route` | Route at index or `null`. |
| `count(): int` | Number of routes. |
| `isEmpty(): bool` | Whether no routes are registered. |
| `findByName(string $name): ?Route` | Named route lookup. |

## RouteMatcher

| Method | Description |
| --- | --- |
| `match(RouteCollection $routes, string $method, string $path): ?array` | Returns `['route' => Route, 'parameters' => array]` or `null`; throws `MethodNotAllowedException` on method mismatch. Expects normalized inputs. |

## Request

See [request.md](request.md) for details.

`method()`, `getMethod()`, `isMethod()`, `path()`, `header()`, `getHeaderLine()`, `query()`, `input()`, `param()`, `getAttribute()`, `all()`, `getQueryParams()`, `getParsedBody()`, `ip()`, `wantsJson()`, `withParams()`, `withAttribute()`, `static capture()`.

## Response and JsonResponse

See [response.md](response.md) for details.

`make()`, `text()`, `html()`, `json()`, `redirect()`, `withStatus()`, `withHeader()`, `withHeaders()`, `getStatus()`, `getHeaders()`, `getHeader()`, `getContent()`, `getBody()`, `send()`.

## Body

| Method | Description |
| --- | --- |
| `__construct(string $content = '')` | Create with initial content. |
| `write(string $data): self` | Append data. |
| `__toString(): string` | Current content. |

## Controller

See [controllers.md](controllers.md) for details.

`setContext()`, `request()`, `response()`, `param()`, `json()`, `text()`, `html()`, `redirect()`.

## Exceptions

| Class | Accessors |
| --- | --- |
| `Kurama\Exceptions\RouteNotFoundException` | `method()`, `path()` |
| `Kurama\Exceptions\MethodNotAllowedException` | `allowedMethods()`, `allowed()`, `method()`, `path()` |
| `InvalidArgumentException` | Thrown for invalid route definitions and URL generation input |
