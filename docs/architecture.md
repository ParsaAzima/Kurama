# Architecture

Kurama is intentionally split into two layers.

## Layers

### Router core

| Class | Responsibility |
| --- | --- |
| `Kurama\Router` | Registration API, normalization, `dispatch()` / `resolve()` / `run()`. |
| `Kurama\Route` | One route: methods, pattern, handler, constraints, name, middleware. |
| `Kurama\RouteCollection` | Ordered storage and named-route lookup. |
| `Kurama\RouteMatcher` | Path/method matching and 404 vs 405 decision. |
| `Kurama\Exceptions\RouteNotFoundException` | No path matched. |
| `Kurama\Exceptions\MethodNotAllowedException` | Path matched, method did not. |

The core has no knowledge of superglobals, HTTP responses, middleware or controllers. `dispatch()` returns raw handler results.

### Application layer

| Class | Responsibility |
| --- | --- |
| `Kurama\AppFactory` | Slim-style `create()` factory. |
| `Kurama\Application` | Route proxies, middleware pipeline, handler invocation, error rendering, `run()`. |
| `Kurama\Request` | Immutable request value object built from superglobals. |
| `Kurama\Response` | Immutable HTTP response value object. |
| `Kurama\JsonResponse` | JSON-encoded response. |
| `Kurama\Body` | Tiny writable body used by `Response::getBody()`. |
| `Kurama\Controller` | Optional base class for grouped actions. |
| `Kurama\Template` | Minimal native-PHP template engine with escaping and partials. |

You can use the router without the application layer, and the application layer without adding any container or provider.

## Request lifecycle

1. `Application::run()` creates a `Request` via `Request::capture()` unless one is passed.
2. `Application::handle()` normalizes the method/path through `Router::resolve()`.
3. `RouteMatcher` finds the first matching route or throws a routing exception.
4. Matched parameters are attached to the request copy via `withParams()`.
5. A fresh default `Response` is created.
6. Global, group and route middleware wrap a final action closure. Controllers receive the request, response and template engine.
7. The action resolves the handler (closure, callable, controller notation), injects arguments and executes it.
8. Non-`Response` results are wrapped with `Response::make()`.
9. Any `Throwable` is rendered by the error handler.
10. `Response::send()` emits status code, headers and body.

## Design rules

- No service container, service providers or event bus.
- No static mutable state. `AppFactory::create()` is a stateless factory.
- No PSR-7 dependency; `Request`, `Response` and `Body` are small local value objects.
- Opt-in layers: using `Router` alone keeps working.
- Every feature ships with tests in the same release.

## Why no DI container?

Controller dependencies are limited to what the router already knows: route parameters, `Request`, `Response` and `$args`. Anything else should be constructed by your application code or passed as an already-created callable. This keeps the request path small and predictable.
