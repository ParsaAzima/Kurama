# Kurama Roadmap

Kurama grows in small, opt-in layers. The router stays the core; everything else is a thin layer on top.

## Design rules

1. The router core stays dependency-free and framework-agnostic.
2. `dispatch()` always returns a handler result; it never sends HTTP output.
3. No global mutable state or hidden singletons; the container is explicit and opt-in.
4. Every feature ships with PHPUnit tests and README documentation in the same release.
5. New layers are opt-in: using `Router` alone keeps working exactly as before.
6. Semantic versioning from v1.0.0 onward; breaking changes are reserved for v2.x.
7. No full-stack ambitions: database, auth, sessions, templates and DI containers are non-goals.

## Status overview

| Version | Theme | Status |
| --- | --- | --- |
| `v0.1.0` | Router core + standalone `run()` | Released |
| `v0.2.0` | Application layer, HTTP, groups, middleware, errors | Released |
| `v0.3.0` | Named routes and constraints | Released |
| `v0.4.0` | Controllers and fast paths | Released |
| `v0.5.0` | Native PHP template engine | Released |
| `v0.6.0` | Phase 3 application features | Released |
| `v0.7.0` | Phase 4 utilities | Released |
| `v0.8.0` | Providers, auto-discovery and events | Released |
| `v0.9.0` | Route caching and unnamed URL generation | Released |
| `v0.10.0` | CLI kernel and automatic OPTIONS | Released |
| `v0.11.0` | Interoperability and hardening | Released |
| `v0.12.0` | Remaining weaknesses | Released |
| `v0.13.0` | Advanced events and providers | Released |
| `v0.14.0` | Long-running worker server | Released |
| `v0.15.0` | Third-party engines and view cache | Released |
| `v1.0.0` | API freeze and stable release | Released |
| `v1.1.0` | Full PSR-7 / PSR-17 support | Released |

Milestones are feature-driven, not date-driven. A version ships when its acceptance criteria are met.

## v0.1.0 — Router core (released)

- PSR-4 library with native PHP/PCRE matching.
- `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any` and multi-method `match()`.
- Ordered `RouteCollection`, dedicated `RouteMatcher`, validated `Route`.
- `RouteNotFoundException` and `MethodNotAllowedException` with `allowedMethods()`.
- URL normalization, trailing-slash policy and safe percent-decoding of parameters.
- `Router::run()` as a thin HTTP entry point plus `public/index.php` front controller.
- PHPUnit suite, router example, README and MIT license.

## v0.2.0 — Application layer (released)

- `Application` as an optional layer: route proxies, `handle()`, `run()`, configuration.
- Immutable `Request` value object with query/body/header/parameter access.
- Immutable `Response` with `text()`, `html()`, `json()`, `redirect()` and fluent `with*` methods.
- `JsonResponse` with correct JSON encoding and content type.
- Route groups with prefix and shared middleware; nested groups supported.
- Route-level middleware via `Route::middleware()`.
- Closure-based global/group/route middleware pipeline with short-circuit support.
- Error rendering: 404, 405 + `Allow`, 500 with debug toggle and custom error handler hook.
- Slim-style developer experience: `AppFactory::create()`, handler signature `($request, $response, $args)`, `$response->getBody()->write()`, `$app->add()` / `$route->add()`.
- Service-free design: no container, no providers, no static global instance, no PSR-7 dependency.

## v0.3.0 — Named routes and constraints (released)

- Named routes with `Route::name()`, `Router::url()` / `Application::url()` and Slim-style `urlFor()` aliases.
- Inline parameter constraints, e.g. `/users/{id:\d+}` and `/posts/{year:\d{4}}/{slug:[a-z-]+}`.
- Constraint validation: no empty match, no `/` match, no backreferences and no regex delimiter.
- URL generation validates values against constraints before percent-encoding them.
- `RouteCollection::findByName()` for inspection.
- All v0.1/v0.2 APIs remain backward compatible.

Explicitly out of scope: global helper functions, DI containers, templates.

## v0.4.0 — Controllers and fast paths (released)

- `Kurama\Controller` base class with request/response context and `json()`, `text()`, `html()`, `redirect()`, `param()` helpers.
- Controller handlers supported as `[Controller::class, 'method']` and `Controller@method`, without a container.
- Reflection metadata cached per `Application` instance for named handlers and controller methods.
- Static routes matched with plain string comparison instead of regex; regexes compiled once at registration.
- Small benchmark script comparing static vs dynamic matching.

## v0.5.0 — Native PHP template engine (released)

- `Kurama\Template` with dot-notation names, partial rendering, explicit escaping and path-traversal protection.
- `Application::render()` and `Controller::view()` helpers.
- Configurable `views.path` with a `<project root>/views` default.
- Example templates in `views/` and full documentation in `docs/templates.md`.

## v0.6.0 — Phase 3 application features (released)

- `Kurama\Config` with dot notation and `config/app.php` loading.
- `Kurama\Environment` with `.env` parsing, quoting rules and non-overwriting defaults.
- `Kurama\Container` with bindings, singletons, factories, auto-wiring, `call()` and circular-dependency detection.
- Controller constructor injection and `Controller::make()` helper.
- Template layouts and shared data via `Template::render(..., $layout)` and `share()`.
- Documentation for configuration, environment and DI.

## v0.7.0 — Phase 4 utilities (released)

- `Kurama\Validator` with rules, grouped errors and 422 rendering.
- `Kurama\Session` with lazy native sessions, flash data and array-backed testing.
- `Kurama\Csrf` with session tokens, header/body validation, middleware and 419 rendering.
- `Kurama\Cookie` and `Response::withCookie()` for outgoing cookies.
- `Kurama\Logger` with level methods and file output.
- Documentation for all five utilities.

## v0.8.0 — Providers, auto-discovery and events (released)

- `ServiceProviderInterface` and `ServiceProvider` with `register()` / `boot()` lifecycle.
- `Application::register()`, `boot()`, `isBooted()` and lifecycle events.
- `ProviderDiscovery` reading `extra.kurama.providers` from the root project and installed packages.
- `Kurama\Events\Dispatcher` with priorities, wildcards and `once()`.
- Opt-in: boot runs once, event dispatch is skipped when no listeners exist.

## v0.9.0 — Route caching and unnamed URL generation (released)

- `RouteCache` exports/imports route definitions to a plain PHP file.
- `Application::loadRoutes()`, `routesFromCache()` and `cacheRoutes()`.
- Class-based handlers and middleware are cacheable; closures are rejected with a clear error.
- `url()` accepts route names, path patterns and `Class@method` handlers; `urlForHandler()` added.
- The in-memory dispatch index and memo remain active after loading a cache.

## v0.10.0 — CLI kernel and automatic OPTIONS (released)

- `Kurama\Console\Console` kernel with `list`, `help`, `version`, `route:list` and `cache:routes`.
- Custom commands extend `Kurama\Console\Command`; `Input` parses positional args and long options.
- `Application::runConsole()` and `bin/kurama` entry point.
- Automatic OPTIONS responses: 204 + `Allow` when a path exists without an explicit OPTIONS route.
- `options.auto` config flag; explicit OPTIONS routes still win.

## v0.11.0 — Interoperability and hardening (released)

- Route cache freshness signatures based on `route.cache.sources`; stale caches rebuild automatically.
- `cache:clear` CLI command.
- Extended validation: custom rules, dotted fields, `url`, `uuid`, `json`, `alpha`, `alpha_num`, `digits`, `between`, `different`, `required_if`.
- Duck-typed PSR-7 bridge and `Application::handlePsr()`; no required PSR dependency.

## v0.12.0 — Remaining weaknesses (released)

- Route cache supports closures through `route.cache.closure_resolver`.
- Optional route parameters `{id?}`, named constraint aliases and implicit HEAD in `Allow`.
- PSR-15 middleware adapter when PSR interfaces are installed.
- Error handler chains via `addErrorHandler()`.
- Logger level thresholds and size rotation.
- GitHub Actions CI: composer validate, syntax checks and PHPUnit.

## v0.13.0 — Advanced events and providers (released)

- Deferred/queued events with FIFO `flush()`, `events.defer` config and `Application::flushEvents()`.
- `ServiceProvider::requires()` with topological register/boot ordering and cycle detection.
- `ServiceProvider::tags()` and `Application::tagged()` for tagged container services.
- `queueEvent()` helper and `app.terminated` flush after `run()`.

## v0.14.0 — Long-running worker server (released)

- `Kurama\Server\WorkerServer` with `stream_select` event loop and keep-alive connections.
- Incremental HTTP parser and response serializer.
- Optional `pcntl` fork workers; graceful single-process fallback.
- `Application::prepareForRequest()` resets session and router memo between iterations.
- `php bin/kurama serve` and `composer serve:worker`.

## v0.15.0 — Third-party engines and view cache (released)

- `RendererInterface`, `CallableRenderer` and extension-based `Renderer`.
- Any Twig/Blade/custom engine can be plugged in without a framework dependency.
- File-backed rendered-view cache with TTL and `view:clear` command.
- `Application::$view`, container bindings and controller `view()` delegation.

## v1.0.0 — API freeze and stable release (released)

- `Kurama\Version` with `1.0.0` constant and PHP 8.3 minimum.
- Frozen public API documented in [docs/api-freeze.md](docs/api-freeze.md).
- `CHANGELOG.md` with the full 1.0.0 summary.
- Stable Composer constraint: `kurama/framework:^1.0`.
- Maintained by **berlium**; version command and package metadata updated.
- CI, benchmarks, 206 PHPUnit tests and full documentation are part of the release gate.

## v1.1.0 — Full PSR-7 / PSR-17 support (released)

- `Kurama\Http` PSR-7 classes and six PSR-17 factories.
- `Application::handlePsr7()` with real `ServerRequestInterface` / `ResponseInterface`.
- `PsrBridge` conversions to/from real PSR-7 messages.
- Backward-compatible additive release; 215 PHPUnit tests.

## Explicit non-goals

These are not planned for Kurama:

- Database access, ORM or query builders.
- Authentication and authorization. Session, cookie, CSRF and template primitives are included.
- Bundled third-party engines; adapters are provided instead.
- Dependency injection containers or service providers.
- Application lifecycle events, event buses or console kernels.
- Becoming a full-stack framework.

If a feature would require one of these to be useful, it does not belong in Kurama.
