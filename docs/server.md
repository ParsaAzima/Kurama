# Long-running worker server

Kurama can serve requests from a persistent PHP process instead of booting the application on every request.

```bash
composer serve:worker
# or
php bin/kurama serve --host=127.0.0.1 --port=8000 --workers=1
```

## Modes

- **Cooperative async (always available):** a single event loop uses `stream_select()` over the listening socket and all client connections. Slow or idle clients do not block other requests.
- **Multi-process (when `pcntl` is available):** `--workers=N` forks independent worker processes sharing the listening socket. PHP's default distribution has no safe threads, so concurrency is process-based, not thread-based. Without `pcntl`, Kurama prints a warning and runs one process.

## Per-request isolation

The application instance stays alive between requests, so Kurama resets state that must not leak:

```php
$app->prepareForRequest(); // session detach + router memo reset
$this->application->handle($request);
```

Providers, container bindings, compiled route caches and middleware remain warm across requests — that is the point of the worker.

## HTTP support

- HTTP/1.1 request line, headers, cookies, query string and `Content-Length` bodies.
- JSON and form-urlencoded body parsing.
- Keep-alive: multiple requests over one connection.
- Automatic `Content-Length` and `Connection` headers.

Chunked request bodies, TLS termination, static file serving and HTTP/2 are intentionally outside this minimal server. Put it behind nginx or a load balancer for production.

## Classes

| Class | Purpose |
| --- | --- |
| `Kurama\Server\WorkerServer` | Event loop, connection lifecycle, optional fork workers. |
| `Kurama\Server\RequestParser` | Incremental HTTP request parser. |
| `Kurama\Server\ResponseWriter` | Serializes a `Response` into HTTP/1.1 bytes. |

## Configuration

```php
'server.host' => '127.0.0.1',
'server.port' => 8000,
'server.workers' => 1,
```

## Stopping

With `pcntl`, `SIGINT` and `SIGTERM` stop the loop. On Windows, terminate the process normally.
