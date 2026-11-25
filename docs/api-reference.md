# API reference

All classes live under the `Kurama` namespace. Exceptions live under `Kurama\Exceptions`.

## AppFactory

| Method | Description |
| --- | --- |
| `static create(array\|Config $config = [], ?string $basePath = null, ?Container $container = null): Application` | Create an application. Keys: `debug`, `timezone`, `views.path`, `env.path`, `env.enabled`, `session.name`, `session.native`, `log.path`. |

## Application

| Method | Description |
| --- | --- |
| `__construct(array\|Config $config = [], ?Router $router = null, ?string $basePath = null, ?Container $container = null)` | Create with config, router, project root and container. |
| `get/post/put/patch/delete/options/head/any(string $pattern, mixed $handler): Route` | Register a route. |
| `match(array\|string $methods, string $pattern, mixed $handler): Route` | Register for one or more methods. |
| `group(array $attributes, callable $callback): void` | Prefix/middleware group; callback receives `Application`. |
| `middleware(callable ...$middleware): self` | Add global middleware. |
| `add(callable ...$middleware): self` | Alias of `middleware()`. |
| `url(string $name, array $params = []): string` | Build URL from a named route. |
| `urlFor(string $name, array $params = []): string` | Alias of `url()`. |
| `config(string $key, mixed $default = null)` / `env(...)` | Read configuration / environment values. |
| `validate(array $data, array $rules): array` | Validate and return validated fields, or throw `ValidationException`. |
| `events(): Dispatcher` / `listen()` | Access the event bus / register a listener. |
| `register(string\|ServiceProviderInterface): self` | Register a provider. |
| `boot(): self` / `isBooted(): bool` | Boot providers once / check state. |
| `render(string $template, array $data = [], int $status = 200, ?string $layout = null): Response` | Render a template into an HTML response. |
| `setErrorHandler(callable $handler): self` | Set custom error renderer. |
| `handle(Request $request): Response` | Dispatch without sending output. |
| `run(?Request $request = null): void` | Dispatch and send response. |
| `router` (public readonly `Router`) | Underlying router. |
| `template` (public readonly `Template`) | Template engine instance. |
| `configuration` (public readonly `Config`) | Configuration repository. |
| `environment` (public readonly `Environment`) | Environment variables. |
| `container` (public readonly `Container`) | DI container. |
| `session`, `csrf`, `logger` (public readonly) | Phase 4 utilities. |

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

## Providers, discovery and events

See [service-providers.md](service-providers.md) and [events.md](events.md).

| Class | Key members |
| --- | --- |
| `ServiceProvider` | `register(Application)`, optional `boot(Application)`. |
| `ProviderDiscovery` | `discover(string $basePath)`, `fromComposer()`, `fromArray()`. |
| `Dispatcher` | `listen()`, `once()`, `wildcard()`, `has()`, `forget()`, `dispatch()`. |

## Validation, session, CSRF, cookies and logging

See [validation.md](validation.md), [session.md](session.md), [csrf.md](csrf.md), [cookie.md](cookie.md) and [logging.md](logging.md).

| Class | Key members |
| --- | --- |
| `Validator` | `make()`, `passes()`, `fails()`, `errors()`, `validated()`. |
| `Session` | `fake()`, `start()`, `get()`, `set()`, `has()`, `remove()`, `all()`, `flush()`, `flash()`, `flashed()`, `regenerate()`, `destroy()`. |
| `Csrf` | `token()`, `field()`, `validate()`, `validateRequest()`, `regenerate()`, `middleware()`. |
| `Cookie` | `delete()`, `toHeader()`; public name/value/attributes. |
| `Logger` | `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()`, `debug()`, `log()`. |
| `HttpException` | `status`, `headers`. |
| `ValidationException` | `errors()`. |

## Container

See [dependency-injection.md](dependency-injection.md) for details.

| Method | Description |
| --- | --- |
| `instance(string $id, mixed $value): void` | Register an existing value. |
| `bind(string $id, Closure\|string\|null $concrete = null, bool $singleton = false): void` | Register a factory/class binding. |
| `singleton(string $id, Closure\|string\|null $concrete = null): void` | Shared binding. |
| `factory(string $id, Closure $factory): void` | Non-shared factory binding. |
| `has(string $id): bool` | Check availability. |
| `get(string $id): mixed` | Resolve or throw `NotFoundException`. |
| `make(string $id, array $parameters = []): mixed` | Resolve with constructor overrides. |
| `call(callable $callback, array $parameters = []): mixed` | Invoke with argument injection. |

## Config

See [configuration.md](configuration.md).

`get()`, `set()`, `has()`, `load()`, `all()`.

## Environment

See [environment.md](environment.md).

`__construct(?string $file = null, bool $overwrite = false)`, `load()`, `get()`, `has()`, `all()`.

## Template

See [templates.md](templates.md) for details.

| Method | Description |
| --- | --- |
| `__construct(string $basePath, string $extension = '.php')` | Create with a views directory. |
| `render(string $template, array $data = []): string` | Render to a string. |
| `path(string $template): string` | Resolve name to file path. |
| `exists(string $template): bool` | Check existence. |
| `escape(string $value): string` | HTML escape. |
| `e(string $value): string` | Alias of `escape()`. |

## Controller

See [controllers.md](controllers.md) for details.

`setContext()`, `request()`, `response()`, `param()`, `json()`, `text()`, `html()`, `redirect()`, `view()`, `template()`.

## Exceptions

| Class | Accessors |
| --- | --- |
| `Kurama\Exceptions\RouteNotFoundException` | `method()`, `path()` |
| `Kurama\Exceptions\MethodNotAllowedException` | `allowedMethods()`, `allowed()`, `method()`, `path()` |
| `InvalidArgumentException` | Thrown for invalid route definitions and URL generation input |
