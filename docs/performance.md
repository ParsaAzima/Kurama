# Performance

Kurama targets small overhead, not a full framework runtime.

## Decisions that keep the hot path small

1. **Compiled once** — each `Route` compiles its regex in the constructor, never during dispatch.
2. **Static fast path** — routes without parameters are matched by plain string comparison before regex.
3. **Cached reflection** — named functions and controller methods reuse their `ReflectionFunction` / `ReflectionMethod` per `Application` instance. Closures are intentionally not cached.
4. **No lifecycle machinery** — no container boot, provider registration, event dispatch or middleware interface resolution.
5. **Normalization at the edge** — method/path normalization happens once in `Router::resolve()`.
6. **No runtime dependencies** — only PHP and Composer autoloading.

## Benchmark

```bash
composer bench
```

`examples/benchmark.php` performs 100,000 dispatches for a static route and 100,000 for a dynamic constrained route, then prints duration and requests per second.

Example output on PHP 8.3 (local machine):

```
Static:  100000 dispatches in 0.0638s (~1.56M req/s)
Dynamic: 100000 dispatches in 0.1090s (~0.92M req/s)
```

These numbers are indicative only. They measure routing and handler invocation in a CLI loop, not network I/O, opcache configuration or real server throughput. The dynamic case is slower because it runs a PCRE match and decodes parameters.

## Practical advice

- Prefer static paths where possible; `/users/new` is cheaper than `/users/{slug}`.
- Keep constraint regexes simple; avoid nested quantifiers.
- Register frequently used routes early — matching stops at the first hit.
- Use `Application::handle()` in tests to avoid sending output.
- Enable OPcache in production.
