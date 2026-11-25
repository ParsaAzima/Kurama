# Events

`Kurama\Events\Dispatcher` is a small synchronous event bus. It has no async queue and no PSR dependency.

## Listening and dispatching

```php
$app->listen('user.created', function (int $id): void {
    // ...
});

$app->events()->dispatch('user.created', 42);
```

`$app->listen()` is a shortcut for `$app->events()->listen()`. Higher priority listeners run first.

```php
$app->events()->listen('user.created', $listener, 10);
$app->events()->once('user.created', $listener);
$app->events()->wildcard('user.*', $listener);
$app->events()->has('user.created');
$app->events()->forget('user.created');
```

Wildcard patterns use `*` as a segment wildcard; `*` alone matches every event.

## Framework events

| Event | Payload |
| --- | --- |
| `app.registering` | `Application`, provider |
| `app.booting` | `Application` |
| `app.booted` | `Application` |
| `request.received` | `Application`, `Request` |
| `response.ready` | `Application`, `Response`, `Request` |
| `app.terminated` | `Application`, `Response` |

`dispatch()` returns immediately when there are no listeners, so the hot path stays cheap.

## Notes

- Listeners run in registration order within the same priority.
- `once()` wraps a listener so it fires only for the first matching dispatch.
- The dispatcher is available as `$app->events()` and `$app->container->get('events')`.
- Listeners are synchronous; do not use them for slow work in the request path.
