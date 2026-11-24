# Controllers

`Kurama\Controller` is an optional base class for grouping related actions. It does not use a DI container.

## Basic controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Kurama\Controller;
use Kurama\Response;

final class UserController extends Controller
{
    public function show(string $id): Response
    {
        return $this->json([
            'id' => $this->param('id'),
            'method' => $this->request()->method(),
        ]);
    }
}
```

Register it in either notation:

```php
$app->get('/users/{id}', [UserController::class, 'show']);
$app->get('/users/{id}', UserController::class . '@show');
```

Both are resolved to an instance and a method by `Application`. Controllers must have a no-argument constructor; if they need dependencies, pass an already-created instance:

```php
$controller = new UserController($repository);
$app->get('/users/{id}', [$controller, 'show']);
```

Constructor arguments are now resolved by the container, so controllers can declare services directly:

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
```

Unresolvable scalar arguments still produce a 500 error. Use `$this->make(Service::class)` for on-demand resolution inside an action.

## Available helpers

| Method | Description |
| --- | --- |
| `request(): Request` | Current request. |
| `response(): Response` | Fresh default response for this action. |
| `param(string $name, mixed $default = null)` | Matched route parameter. |
| `json(mixed $data, int $status = 200, array $headers = [])` | `JsonResponse`. |
| `text(...)`, `html(...)` | Text/HTML responses. |
| `redirect(string $url, int $status = 302)` | Redirect response. |
| `view(string $template, array $data = [], int $status = 200, ?string $layout = null)` | Render a native PHP template into an HTML response. |
| `make(string $abstract, array $parameters = [])` | Resolve a service from the container. |

`setContext(Request, Response, ?Template, ?Container)` is called by the framework before the action. You normally do not call it yourself. The template instance makes `view()` work; the container enables `make()`; see [templates.md](templates.md) and [dependency-injection.md](dependency-injection.md).

## Handler arguments

Controller methods can use the same injection rules as closures:

```php
public function show(string $id): Response          // route param by name
public function index(Request $request): Response  // request by type
public function store(array $args, Response $response): Response // $args + response
public function legacy(Request $request, Response $response, array $args): Response
```

Resolution priority: `$request` / `Request`, `$response` / `Response`, `$args`, route parameter by name, default value. Unresolvable required arguments raise a 500 error.

## Writing to the response

```php
public function index(Request $request, Response $response): Response
{
    $response->getBody()->write('Users');

    return $response;
}
```

Return the same response you wrote to. If you return a different value, Kurama wraps that value instead.
