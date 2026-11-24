# Performance

Kurama targets small overhead, not a full framework runtime.

## Decisions that keep the hot path small

1. **Compiled once** — each `Route` compiles its regex in the constructor, never during dispatch.
2. **Hash index for static routes** — exact paths are looked up by method + path in O(1), not scanned one by one.
3. **Order-safe dynamic filtering** — only dynamic routes registered *before* the earliest matching static route are checked, preserving registration order.
4. **Segment pre-filter** — a path with a different number of `/` separators is rejected before PCRE runs.
5. **Cached resolution plans** — the container caches argument plans and constructor plans, avoiding repeated `ReflectionParameter` traversal. Closures are intentionally not cached.
6. **Path cache** — each `Request` normalizes its URI at most once.
7. **No lifecycle machinery** — no providers, event dispatch or middleware interface resolution in the hot path.
8. **No runtime dependencies** — only PHP and Composer autoloading.

## Benchmark

```bash
composer bench
```

`examples/benchmark.php` performs:

- 100,000 dispatches for a static route.
- 100,000 for a dynamic constrained route.
- 20,000 dispatches against a router with 201 registered routes.

Example output on PHP 8.3 (local machine):

```
Static:     100000 dispatches in 0.0547s (~1.83M req/s)
Dynamic:    100000 dispatches in 0.0994s (~1.01M req/s)
201 routes:  20000 dispatches in 0.0116s (~1.72M req/s)
```

The large-set case is now O(1) for exact static paths thanks to the hash index; before the index it required a linear scan of all 201 routes.

These numbers are indicative only. They measure routing and handler invocation in a CLI loop, not network I/O, opcache configuration or real server throughput. The dynamic case is slower because it runs PCRE and decodes parameters.

## Practical advice

- Prefer static paths where possible; `/users/new` is cheaper than `/users/{slug}`.
- Keep constraint regexes simple; avoid nested quantifiers.
- Register frequently used routes early — matching stops at the first hit.
- Use `Application::handle()` in tests to avoid sending output.
- Enable OPcache in production.
