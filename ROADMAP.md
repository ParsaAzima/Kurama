# Kurama Roadmap

Kurama grows in small, opt-in layers. The router stays the core; everything else must earn its place by being small, testable and optional.

## Design rules

1. The router core stays dependency-free and framework-agnostic.
2. `dispatch()` always returns a handler result; it never sends HTTP output.
3. No global mutable state, no service locator, no hidden singletons.
4. Every feature ships with PHPUnit tests and README documentation in the same release.
5. New layers are opt-in: using `Router` alone must keep working exactly as before.
6. Semantic versioning after v1.0.0; before that, minor releases may refine the API with migration notes.
7. No full-stack ambitions: database, auth, sessions, templates and DI containers are non-goals.

## Status overview

| Version | Theme | Status |
| --- | --- | --- |
| `v0.1.0` | Router core + HTTP entry point | Released |
| `v0.2.0` | Router completeness | Next |
| `v0.3.0` | Minimal HTTP value objects | Planned |
| `v0.4.0` | Closure-based middleware | Planned |
| `v0.5.0` | Error handling hooks | Planned |
| `v0.6.0` | Developer tooling (dev-only) | Planned |
| `v1.0.0` | Stable API | Planned |

Milestones are feature-driven, not date-driven. A version ships when its acceptance criteria are met.

## v0.1.0 — Router core (released)

- PSR-4 library with native PHP/PCRE matching.
- `get`, `post`, `put`, `patch`, `delete`, `options`, `head`, `any` and multi-method `match()`.
- Ordered `RouteCollection`, dedicated `RouteMatcher`, validated `Route`.
- `RouteNotFoundException` and `MethodNotAllowedException` with `allowedMethods()`.
- URL normalization, trailing-slash policy and safe percent-decoding of parameters.
- `Router::run()` as a thin HTTP entry point plus `public/index.php` front controller.
- PHPUnit suite, example app, README and MIT license.

## v0.2.0 — Router completeness (next)

Goal: finish routing features without introducing an HTTP layer.

- Named routes with `Router::url(string $name, array $params = []): string`.
- Optional inline parameter constraints, e.g. `/users/{id:\d+}`, with strict validation so unsafe regex cannot be injected.
- `RouteCollection::findByName()` for inspection.
- `Allow` header includes implicit `HEAD` whenever `GET` is allowed.
- CI checks: `php -l`, PHPUnit, and a PSR-12 style check.
- Small benchmark script comparing static vs dynamic matching.

Acceptance criteria:

- All v0.1 APIs remain backward compatible.
- Constraints cannot match across `/` unless explicitly written to do so.
- Tests for named URLs, constraints, `HEAD` in `Allow`, and malformed constraint definitions.

Explicitly out of scope: Request/Response, middleware, named-route helpers in global functions.

## v0.3.0 — Minimal HTTP value objects

Goal: give `run()` and middleware a clean contract, without turning Kurama into a full-stack framework.

- Immutable `Request` value object built from superglobals.
- Immutable `Response` with `text()`, `html()`, `json()` and `redirect()` factories.
- `Router::dispatch()` remains unchanged and may still return any value; `run()` adapts results into responses.
- Clear separation: routing knows nothing about superglobals; the entry point builds the `Request`.

Acceptance criteria:

- Existing routes keep working without modification.
- Response headers are never sent from constructors or factories.
- Tests cover JSON encoding, status codes and header handling.

Non-goals: sessions, cookies, file uploads, PSR-7 interoperability.

## v0.4.0 — Closure-based middleware

Goal: cross-cutting HTTP behavior with no container and no magic.

- Middleware signature: `fn ($request, $next) => $response`.
- Global middleware on `run()` plus per-route middleware via `Route::middleware(...)`.
- Simple pipeline built with `array_reduce`, no middleware interface required.
- Optional prefix-only route groups for organizing larger route files.
- Documented order of execution and short-circuit behavior.

Acceptance criteria:

- Middleware can modify the response, short-circuit, or replace the request.
- No global state; pipeline state is local to one `run()` call.
- Tests cover ordering, short-circuiting and route-level only middleware.

## v0.5.0 — Error handling hooks

Goal: let applications customize error output without coupling exceptions to responses.

- Configurable error handler callable passed to `Router::run()`.
- Default behavior remains the v0.1 status codes and plain messages.
- Exception-to-status mapping for common runtime failures.
- Optional structured problem details for JSON responses.

Acceptance criteria:

- Throwing from a handler produces a controlled response, not a fatal error.
- The default handler is unchanged unless a custom one is supplied.
- Tests cover 404, 405, handler exceptions and custom handlers.

## v0.6.0 — Developer tooling

Goal: improve day-to-day workflow without adding runtime dependencies.

- `route:list` dev command that prints registered methods, patterns and names.
- Improved built-in server script with static-file handling and clean shutdown.
- Migration guide from v0.1 to v0.2+.

Acceptance criteria:

- Tooling lives outside `src/` and is not autoloaded at runtime.
- Documentation clearly separates dev tools from the library.

## v1.0.0 — Stable API

Goal: freeze the public API and commit to semantic versioning.

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
- Application lifecycle classes, event buses or console kernels.
- Becoming a full-stack framework.

If a feature would require one of these to be useful, it does not belong in Kurama.
