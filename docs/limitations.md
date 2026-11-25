# Limitations and non-goals

Kurama is a router-first micro framework. The following are intentionally absent.

## Not implemented

- Database access, ORM or query builders.
- Authentication and authorization. Session and cookie primitives are included, but no user provider or guard.
- Third-party template engines (Twig, Blade); only the built-in native-PHP engine is supported.
- Layout inheritance, template caching and auto-escaping by default; call `escape()` / `e()` explicitly.
- Container tags, compiler passes and PSR-11 interface dependencies; the container stays explicit.
- Console kernels and a full event subsystem with queued/async listeners; the event bus is synchronous and in-process.
- PSR-7 / PSR-15 interoperability. The style is inspired by Slim, but the value objects are local and dependency-free.
- Global helper functions such as `app()` or `url_for()`; use the instance methods instead.
- Automatic OPTIONS responses. Route caching and URL generation for unnamed routes are included.
- Full CLI tooling; `composer serve` and `composer bench` are development conveniences only.

## Deliberate simplifications

- `Body` is not a stream; it supports `write()` and string casting only.
- `Request` parses JSON and form bodies itself; there is no body-parser middleware layer.
- Controller dependencies must be passed manually or injected by subclassing; there is no container.
- Error rendering is a single callable hook, not a chain of renderers.
- Route constraints are regex fragments with safety checks, not a full validation library.

## Planned, not promised

See [ROADMAP.md](../ROADMAP.md) for the current plan:

- CI and PSR-12 checks.
- `Allow` header including implicit HEAD.
- `route:list` dev command.
- API freeze and v1.0.0.

Anything not in the roadmap and not in `src/` should be treated as out of scope.
