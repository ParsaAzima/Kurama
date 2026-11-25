# Service providers and auto-discovery

Providers are the only supported way to group boot-time setup. They run once, before the first request.

## Defining a provider

```php
use Kurama\Application;
use Kurama\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(Application $app): void
    {
        $app->container->singleton(MyService::class);
    }

    public function boot(Application $app): void
    {
        $app->middleware($app->csrf->middleware());
        $app->listen('response.ready', fn (Application $app, Response $response) => /* ... */);
    }
}
```

`register()` should only bind services. Other providers may not be registered yet. `boot()` runs after every provider has been registered.

Extending `Kurama\ServiceProvider` is optional: you may implement `Kurama\Contracts\ServiceProviderInterface` directly.

## Registering providers

Explicitly:

```php
$app->register(new AppServiceProvider());
// or
$app->register(AppServiceProvider::class);
```

Via configuration:

```php
// config/app.php
return [
    'providers' => [AppServiceProvider::class],
];
```

## Auto-discovery

Set `discovery.enabled` (default `true`) and add provider classes to `extra.kurama.providers` in any composer package:

```json
{
    "extra": {
        "kurama": {
            "providers": ["Vendor\\Package\\PackageServiceProvider"]
        }
    }
}
```

Kurama reads the root project's `composer.json` and `vendor/composer/installed.json`, merging all declared providers and removing duplicates. Disable with:

```php
$app = AppFactory::create(['discovery.enabled' => false]);
```

`Kurama\ProviderDiscovery::discover($basePath)` and `fromArray($extra)` are public if you need the list yourself.

## Lifecycle

- `Application::register()` dispatches `app.registering`.
- `Application::boot()` dispatches `app.booting`, calls each provider's `boot()`, then dispatches `app.booted`.
- `handle()` calls `boot()` automatically; calling `boot()` manually is safe and idempotent.
- `Application::isBooted()` reports the state.
