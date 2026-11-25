# API freeze and versioning

Kurama `1.0.0` is the first stable release. The public API documented under [`api-reference.md`](api-reference.md) is frozen for the entire `1.x` line.

## Promise

- No breaking changes to public class names, method signatures or behavior in `1.x` minor/patch releases.
- New features are additive and default to the existing behavior.
- Deprecated APIs remain functional for at least one minor release before removal in `2.0.0`.
- Security fixes may be released as patches at any time.

## Semantic versioning

| Change | Version |
| --- | --- |
| Bug fix, security fix | `1.0.x` |
| Backward-compatible feature | `1.x.0` |
| Breaking API change | `2.0.0` |

## Stable public surface

These are the supported extension points:

- `Kurama\Application`, `Router`, `Route`, `RouteCollection`, `RouteMatcher`.
- `Kurama\Request`, `Response`, `JsonResponse`, `Body`, `Cookie`, `Controller`.
- `Kurama\Config`, `Environment`, `Container`, `Validator`, `Session`, `Csrf`, `Logger`, `Template`.
- `Kurama\ServiceProvider` and `Kurama\Contracts\*`.
- `Kurama\View\Renderer`, `CallableRenderer`, `ViewCache`.
- `Kurama\Server\WorkerServer`, `RequestParser`, `ResponseWriter`.
- `Kurama\Events\Dispatcher`, `Kurama\Console\Console`, `Kurama\Console\Command`.
- `Kurama\Bridge\PsrBridge`, `Kurama\Version`.

Anything not in `src/` or not documented is internal and may change.

## Installing

```bash
composer require kurama/framework:^1.0
```

The package is maintained by **berlium**. Source and releases: `https://github.com/ParsaAzima/Kurama`.

## Reporting compatibility issues

If a `1.x` update changes documented behavior, open an issue with the exact version pair and a minimal reproduction. It will be treated as a regression.
