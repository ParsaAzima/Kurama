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
Static:     100000 dispatches in 0.0076s (~13.07M req/s)
Dynamic:    100000 dispatches in 0.0993s (~1.01M req/s)
201 routes:  20000 dispatches in 0.0016s (~12.61M req/s)
```

The large-set case is O(1) for exact static paths thanks to the hash index, and repeated identical dispatches hit the last-request memo (a single callable invocation). Before these optimizations it required a linear scan of all 201 routes.

These numbers are indicative only. They measure routing and handler invocation in a CLI loop, not network I/O, opcache configuration or real server throughput. The dynamic case is slower because it runs PCRE and decodes parameters.

## Comparison with other PHP stacks

Measured on the same machine with PHP 8.3.30, 201 registered routes, 20,000 iterations, 500-iteration warmup, a no-op handler and reused request objects. Two separate categories were timed:

| Layer | Library | req/s | Relative |
| --- | --- | --- | --- |
| Routing core | Native array lookup | ~18.8M | 1.8x faster than Kurama Router |
| Routing core | Kurama Router | ~10.6M | baseline |
| Routing core | FastRoute 1.3 | ~5.9M | ~1.8x slower than Kurama Router |
| Full handling | Kurama Application | ~581k | baseline |
| Full handling | Slim 4.15 | ~212k | ~2.7x slower than Kurama |
| Full handling | Laravel 11 `Illuminate\Routing\Router` | ~2.8k | ~200x slower than Kurama |

Important caveats:

- The Kurama Router number includes last-request memoization: repeated identical `method + URI` dispatches skip normalization and lookup entirely. This is a deliberate space-for-time trade-off and is realistic for hot URLs and this benchmark loop. With alternating URLs, the static hash index fallback is ~3.5M req/s and dynamic routes ~1.0M req/s — in that mixed workload FastRoute's single precompiled regex is faster on dynamic routes.
- FastRoute is a router-only library, so it is directly comparable to `Kurama\Router`, not to `Application`.
- Slim 4 creates PSR-7 request/response objects and runs a PSR-15 middleware dispatcher; Kurama uses its own small value objects and closure pipeline. The numbers are representative of each framework's request path, not identical operations.
- The Laravel figure is a bare `Illuminate\Routing\Router` dispatch through its container, event dispatcher and middleware pipeline. It is **not** a booted Laravel application with `route:cache`, OPcache or server-level tuning, and should be read as an order-of-magnitude reference only.
- None of these CLI numbers include network I/O, a real web server, database access or template rendering. Real application throughput is usually dominated by those layers.

## Practical advice

- Prefer static paths where possible; `/users/new` is cheaper than `/users/{slug}`.
- Keep constraint regexes simple; avoid nested quantifiers.
- Register frequently used routes early — matching stops at the first hit.
- Use `Application::handle()` in tests to avoid sending output.
- Enable OPcache in production.
