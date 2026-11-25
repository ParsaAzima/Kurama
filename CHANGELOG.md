# Changelog

Kurama follows [Semantic Versioning](https://semver.org/). This file records user-visible changes.

## 1.1.0 — 2026-11-26

Additive release, fully backward compatible with 1.0.0.

- Full PSR-7 implementation: `Kurama\Http\Uri`, `Stream`, `Request`, `ServerRequest`, `Response`, `UploadedFile`.
- PSR-17 factories for URI, stream, request, server request, response and uploaded files.
- `Application::handlePsr7(ServerRequestInterface): ResponseInterface`.
- `PsrBridge` now converts to/from real PSR-7 messages.
- Added `psr/http-message` and `psr/http-factory` runtime dependencies.

## 1.0.0 — 2026-11-25

First stable release. Public API is frozen for the 1.x line. Built by **berlium**.

### Core

- PSR-4 `Kurama\` library, PHP 8.3+, no runtime dependencies.
- `Router` with all HTTP methods, `ANY`, multi-method `match()`, groups, named routes, optional parameters and named constraint aliases.
- `Route`, `RouteCollection`, `RouteMatcher` with explicit 404/405 behavior.
- Persistent route cache with source signatures, closure resolver and `cache:routes` / `cache:clear`.
- URL generation by name, path pattern or handler.

### Application layer

- `Application` with middleware pipeline, middleware order, short-circuiting and handler injection.
- `Request`, `Response`, `JsonResponse`, `Body`, `Cookie`, `Controller`.
- Config repository, `.env` loader, container with auto-wiring, service providers with dependencies and tags.
- Synchronous event bus with priorities, wildcards, `once()` and deferred queue.
- Error handler chain, 404/405/419/422 rendering and PSR-7 bridge.

### Utilities

- Validator with custom rules, dotted fields and grouped errors.
- Session with flash data, CSRF protection, file logging with rotation.
- Native PHP template engine plus third-party engine adapters and rendered-view cache.
- CLI kernel with `list`, `help`, `version`, `route:list`, `cache:routes`, `cache:clear`, `serve`, `view:clear`.
- Long-running worker server with cooperative async I/O and optional `pcntl` workers.

### Quality

- 206 PHPUnit tests, GitHub Actions CI, `composer validate`, PSR-12-style source, full Markdown documentation.
- Benchmark suite under `examples/benchmark.php`.

See [docs/api-freeze.md](docs/api-freeze.md) for compatibility guarantees.
