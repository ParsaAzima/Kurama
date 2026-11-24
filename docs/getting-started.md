# Getting started

## Requirements

- PHP 8.3 or newer
- Composer

There are no runtime dependencies. PHPUnit is a development dependency only.

## Install

```bash
composer require kurama/framework
```

To work on this repository itself:

```bash
composer install
composer test
composer serve
```

`composer serve` starts PHP's built-in server at `http://127.0.0.1:8000` using `public/index.php` as the front controller.

## Your first application

Create `public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kurama\AppFactory;
use Kurama\Request;
use Kurama\Response;

$app = AppFactory::create(['debug' => true]);

$app->get('/hello/{name}', function (Request $request, Response $response, array $args): Response {
    $response->getBody()->write('Hello, ' . $args['name'] . '!');

    return $response;
});

$app->run();
```

Visit `http://127.0.0.1:8000/hello/kurama`.

## Using the router alone

If you only need routing and want to control output yourself:

```php
require __DIR__ . '/vendor/autoload.php';

use Kurama\Router;

$router = new Router();
$router->get('/users/{id}', fn (string $id): string => "User ID: {$id}");

echo $router->dispatch('GET', '/users/42');
```

`dispatch()` returns the handler result. It never sends headers or output. `Router::run()` is available as a thin standalone HTTP adapter.

## Composer scripts

| Script | Command |
| --- | --- |
| `composer test` | Run the PHPUnit suite. |
| `composer serve` | Start the built-in server with `public/index.php`. |
| `composer bench` | Run the routing benchmark. |

## Directory layout

```
src/           Framework source (Kurama namespace)
public/        Front controller
tests/         PHPUnit tests
examples/      Runnable examples
docs/          This documentation
ROADMAP.md     Planned versions
```

## Next steps

- Learn the [routing model](routing.md).
- Add [middleware](middleware.md) or [controllers](controllers.md).
- Read the [API reference](api-reference.md) when you need exact signatures.
