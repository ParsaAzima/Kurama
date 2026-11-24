# Kurama documentation

Kurama has two layers: a standalone, side-effect-free `Router` core and an optional `Application` layer that adds dependency injection, configuration, environment variables, HTTP requests/responses, middleware, controllers, templates and error rendering.

Start here:

| Topic | File |
| --- | --- |
| Installation and first app | [getting-started.md](getting-started.md) |
| Layer model and request lifecycle | [architecture.md](architecture.md) |
| Routes, parameters, constraints, groups, named URLs | [routing.md](routing.md) |
| Middleware pipeline | [middleware.md](middleware.md) |
| Controllers | [controllers.md](controllers.md) |
| Native PHP templates | [templates.md](templates.md) |
| Dependency injection | [dependency-injection.md](dependency-injection.md) |
| Configuration | [configuration.md](configuration.md) |
| Environment variables | [environment.md](environment.md) |
| Request object | [request.md](request.md) |
| Response and JSON response | [response.md](response.md) |
| Exceptions and error rendering | [errors.md](errors.md) |
| Performance decisions and benchmark | [performance.md](performance.md) |
| Full public API | [api-reference.md](api-reference.md) |
| Tests | [testing.md](testing.md) |
| What is intentionally missing | [limitations.md](limitations.md) |

The repository [README.md](../README.md) is the short overview. [ROADMAP.md](../ROADMAP.md) tracks planned versions. For exact behavior, the source in `src/` and tests in `tests/` are authoritative.
