# Limitations and non-goals

Kurama is a router-first micro framework. The following are still intentionally absent or simplified.

## Not implemented

- Database access, ORM or query builders.
- Authentication and authorization. Session and cookie primitives are included, but no user provider or guard.
- Third-party template engines (Twig, Blade); only the built-in native-PHP engine is supported.
- Template auto-escaping, real inheritance and component slots; call `escape()` / `e()` and use manual layouts.
- Container tags, compiler passes and PSR-11 interface dependencies; the container stays explicit.
- True background processes for events. Deferred/queued events flush at the end of `run()`; provider dependencies are ordered and tags are resolved on demand.
- Full PSR-7 implementation. A duck-typed bridge and a PSR-15 middleware adapter exist when those interfaces are installed, but Kurama's own objects are not PSR interfaces.
- Global helper functions such as `app()` or `url_for()`; use the instance methods instead.
- Real OS threads and HTTP/2/TLS in the built-in worker. It uses cooperative async I/O and optional `pcntl` processes; use a reverse proxy for TLS/HTTP2 in production.

## Deliberate simplifications

- `Body` is not a stream; it supports `write()` and string casting only. PSR conversion materializes the full string.
- `Request` parses JSON and form bodies itself; there is no body-parser middleware layer.
- Controllers are resolved by the small container, but there is no automatic interface-to-implementation binding convention.
- Route constraints are regex fragments with safety checks; named aliases like `int` and `slug` are available.
- Session storage is native PHP only; CSRF uses a single session token without per-form scoping.
- Logger writes file lines with rotation and a level threshold, but does not implement PSR-3 placeholders.

## Resolved from the original limitation list

- CLI kernel, automatic OPTIONS responses, route caching, unnamed URL generation, providers, auto-discovery and events are all implemented.
- Cache invalidation, closure resolvers, error handler chains and logger rotation are now included.

## Planned, not promised

See [ROADMAP.md](../ROADMAP.md) for the current plan: API freeze, stable release tagging and dev tooling. Anything not in the roadmap and not in `src/` should be treated as out of scope.
