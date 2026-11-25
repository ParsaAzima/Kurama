# Configuration

Configuration is a simple dot-notation repository. There is no config caching step.

## Config file

`Application` loads `<project root>/config/app.php` automatically when it exists:

```php
<?php

return [
    'app' => ['name' => 'Kurama'],
    'debug' => true,
    'timezone' => 'UTC',
    'env.enabled' => true,
    'views.path' => dirname(__DIR__) . '/views',
];
```

Values passed to the constructor override file values:

```php
$app = AppFactory::create(['debug' => false]);
$app = AppFactory::create(new Kurama\Config(['app' => ['name' => 'Custom']]));
```

## Reading and writing

```php
$app->config('app.name');             // Kurama
$app->config('app.missing', 'fallback');
$app->configuration->set('views.path', '/tmp/views');
```

The underlying `Kurama\Config` object:

```php
$config = $app->configuration;
$config->get('app.name');
$config->set('app.name', 'Custom');
$config->has('app.name');
$config->load(['debug' => false]);
$config->all();
```

Dotted keys passed to `load()` are normalized into nested arrays, so `['views.path' => '/views']` is readable as `get('views.path')`.

## Recognized keys

| Key | Meaning | Default |
| --- | --- | --- |
| `debug` | Include exception details in error responses. | `false` |
| `timezone` | Passed to `date_default_timezone_set()`. | PHP default |
| `views.path` | Template directory. | `<project root>/views` |
| `env.path` | `.env` file location. | `<project root>/.env` |
| `env.enabled` | Whether to auto-load `.env`. | `true` |
| `session.name` | PHP session name. | `kurama_session` |
| `session.native` | Use native PHP sessions. | `true` |
| `log.path` | Log file location. | `<project root>/storage/logs/app.log` |
| `log.level` | Minimum level emitted. | `debug` |
| `log.max_bytes` | Rotate file when size reached; 0 disables. | `0` |
| `route.cache.closure_resolver` | Optional callable mapping closures ↔ ids. | none |
| `providers` | Explicit provider class list. | `[]` |
| `discovery.enabled` | Read providers from composer extras. | `true` |
| `route.cache.enabled` | Write/load compiled route definitions. | `false` |
| `route.cache.path` | Route cache file. | `<project root>/storage/cache/routes.php` |
| `route.cache.sources` | Files/directories watched for staleness. | `config/` and `routes/` |
| `options.auto` | Auto-answer `OPTIONS` with 204 + `Allow`. | `true` |
| `events.defer` | Queue events until `flushEvents()` / end of `run()`. | `false` |

Other keys are stored untouched and can be used by your own code via `$app->config(...)` or by injecting `Kurama\Config`.

## Container access

```php
$config = $app->container->get('config');
$config = $app->container->get(Kurama\Config::class);
```
