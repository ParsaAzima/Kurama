# Kurama Roadmap

Kurama grows in small, opt-in layers. The router stays the core; everything else is a thin layer on top.

## Design rules

1. The router core stays dependency-free and framework-agnostic.
2. `dispatch()` always returns a handler result; it never sends HTTP output.
3. No global mutable state or hidden singletons; the container is explicit and opt-in.
4. Every feature ships with PHPUnit tests and README documentation in the same release.
5. New layers are opt-in: using `Router` alone keeps working exactly as before.
6. Semantic versioning after v1.0.0; before that, minor releases may refine the API with migration notes.
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
| `v0.8.0` | Developer tooling (dev-only) | Next |
| `v1.0.0` | Stable API | Planned |

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

## v0.8.0 — Developer tooling (next)

Goal: improve workflow and quality gates without adding runtime dependencies.

- CI checks: `php -l`, PHPUnit and a PSR-12 style check.
- `Allow` header includes implicit `HEAD` whenever `GET` is allowed.
- `route:list` dev command that prints registered methods, patterns and middleware.
- Migration guide from v0.1 to v0.3+.

Acceptance criteria:

- Tooling lives outside `src/` and is not autoloaded at runtime.
- Documentation clearly separates dev tools from the library.

## v1.0.0 — Stable API (planned)

- API review and final naming decisions.
- Performance benchmarks published with methodology.
- Complete migration notes from `v0.x`.
- Long-term support policy for the `v1` line.

## Explicit non-goals

These are not planned for Kurama:

- Database access, ORM or query builders.
- Authentication, authorization, sessions or cookies.
- Template engines or view layers.
- Dependency injection containers or service providers.
- Application lifecycle events, event buses or console kernels.
- Becoming a full-stack framework.

If a feature would require one of these to be useful, it does not belong in Kurama.
