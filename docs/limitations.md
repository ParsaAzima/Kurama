# Limitations and non-goals

Kurama is a router-first micro framework. The following are intentionally absent.

## Not implemented

- Database access, ORM or query builders.
- Authentication, authorization, sessions or cookies.
- Template engines or view layers.
- Dependency injection containers or service providers.
- Application lifecycle events, event buses or console kernels.
- PSR-7 / PSR-15 interoperability. The style is inspired by Slim, but the value objects are local and dependency-free.
- Global helper functions such as `app()` or `url_for()`; use the instance methods instead.
- Route caching, named-route compilers or automatic OPTIONS responses.
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
