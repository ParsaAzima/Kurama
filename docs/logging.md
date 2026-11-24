# Logging

`Kurama\Logger` is a minimal file logger with PSR-3-style level methods, without a PSR dependency.

## Configuration

```php
$app = AppFactory::create([
    'log.path' => __DIR__ . '/../storage/logs/app.log',
]);
```

The directory is created on first write. The logger is available as `$app->logger` and `$app->container->get('logger')`.

## Usage

```php
$app->logger->info('User registered', ['id' => 42]);
$app->logger->warning('Slow query', ['ms' => 850]);
$app->logger->error('Payment failed', ['order' => 'ABC-123']);
```

Levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`. A generic `log($level, $message, $context)` is also available.

## Format

```
[2026-11-25 10:15:32] INFO User registered {"id":42}
```

Context is JSON-encoded with unescaped Unicode and slashes.

## Notes

- Writes are appended with `LOCK_EX`.
- Failures to create directories or write files are suppressed — logging must not break a request.
- Unhandled 500 errors still use `error_log()`; bind or wrap the logger if you want them in the same file.
