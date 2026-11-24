# Kurama Roadmap

Kurama grows in small, opt-in layers. The router stays the core; everything else is a thin layer on top.

## Design rules

1. The router core stays dependency-free and framework-agnostic.
2. `dispatch()` always returns a handler result; it never sends HTTP output.
3. No global mutable state, no service locator, no hidden singletons.
4. Every feature ships with PHPUnit tests and README documentation in the same release.
5. New layers are opt-in: using `Router` alone keeps working exactly as before.
6. Semantic versioning after v1.0.0; before that, minor releases may refine the API with migration notes.
7. No full-stack ambitions: database, auth, sessions, templates and DI containers are non-goals.

## Status overview

| Version | Theme | Status |
| --- | --- | --- |
| `v0.1.0` | Router core + standalone `run()` | Released |
| `v0.2.0` | Application layer, HTTP, groups, middleware, errors | Released |
| `v0.3.0` | Router completeness | Next |
| `v0.4.0` | Developer tooling (dev-only) | Planned |
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
- Service-free design: no container, no providers, no static global instance.

## v0.3.0 — Router completeness (next)

Goal: finish routing features without adding new architectural layers.

- Named routes with `Router::url(string $name, array $params = []): string`.
- Optional inline parameter constraints, e.g. `/users/{id:\d+}`, with strict validation so unsafe regex cannot be injected.
- `RouteCollection::findByName()` for inspection.
- `Allow` header includes implicit `HEAD` whenever `GET` is allowed.
- CI checks: `php -l`, PHPUnit and a PSR-12 style check.
- Small benchmark script comparing static vs dynamic matching.

Acceptance criteria:

- All v0.1/v0.2 APIs remain backward compatible.
- Constraints cannot match across `/` unless explicitly written to do so.
- Tests for named URLs, constraints, `HEAD` in `Allow`, and malformed constraint definitions.

Explicitly out of scope: global helper functions, DI containers, templates.

## v0.4.0 — Developer tooling (planned)

Goal: improve workflow without adding runtime dependencies.

- `route:list` dev command that prints registered methods, patterns and middleware.
- Improved built-in server script with static-file handling and clean shutdown.
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
