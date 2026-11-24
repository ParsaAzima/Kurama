# Dependency injection

Kurama includes a small PSR-11-style container. It is opt-in, has no service providers and does not depend on PSR interfaces.

## Accessing the container

```php
$app = AppFactory::create();
$container = $app->container;
```

Core services are already bound:

| Id | Value |
| --- | --- |
| `container`, `Kurama\Container` | The container itself |
| `app`, `Kurama\Application` | The application |
| `router`, `Kurama\Router` | The router |
| `template`, `Kurama\Template` | The template engine |
| `config`, `Kurama\Config` | Configuration repository |
| `env`, `Kurama\Environment` | Environment variables |

Request and response instances are bound at the start of each `handle()` call.

## Binding

```php
$container->instance('api.key', 'secret');

$container->bind(Formatter::class, JsonFormatter::class);
$container->singleton(Cache::class, fn () => new ArrayCache());
$container->factory(Id::class, fn () => new Id(uniqid()));
```

| Method | Behavior |
| --- | --- |
| `instance($id, $value)` | Store an existing value, including scalars. |
| `bind($id, $concrete = null, $singleton = false)` | Resolve each time unless singleton. |
| `singleton($id, $concrete = null)` | Resolve once and cache. |
| `factory($id, Closure $factory)` | Explicit non-shared factory. |
| `has($id)` | Check instances, bindings or instantiable classes. |
| `get($id)` | Resolve or throw `NotFoundException`. |
| `make($id, $parameters = [])` | Resolve with optional constructor overrides. |
| `call($callable, $parameters = [])` | Invoke with argument injection. |

## Auto-wiring

Classes with type-hinted constructor dependencies are built automatically:

```php
final class UserService
{
    public function __construct(private readonly Config $config)
    {
    }
}

$service = $container->make(UserService::class);
// Config is injected from the container.
```

Scalar parameters must be provided through `$parameters` or have defaults. Circular dependencies throw `ContainerException`.

## Injecting into handlers

Handler arguments are resolved in this order:

1. A value with the same name in `$parameters` (route params, `request`, `response`, `args`).
2. A class/interface type resolvable by the container.
3. A default value.
4. Otherwise `ContainerException`.

```php
$app->get('/users/{id}', function (string $id, Request $request, UserService $users): Response {
    return Response::json($users->find($id));
});
```

## Injecting into controllers

Controller classes are built through the container, so constructor injection works:

```php
final class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function show(string $id): Response
    {
        return $this->json($this->users->find($id));
    }
}

$app->get('/users/{id}', [UserController::class, 'show']);
```

Inside a controller, `$this->make(UserService::class)` resolves a service on demand.
