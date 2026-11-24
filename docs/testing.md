# Testing

Kurama uses PHPUnit and has no integration-test bootstrap beyond Composer autoloading.

## Run the suite

```bash
composer install
composer test
# or: vendor/bin/phpunit
```

Configuration lives in `phpunit.xml`. The suite currently has 110 tests and 194 assertions.

## Test files

| File | Covers |
| --- | --- |
| `tests/RouterTest.php` | All HTTP methods, params, constraints, named URLs, groups, matching rules, 404/405, `run()`. |
| `tests/RouteMatcherTest.php` | Matching results, allowed methods, ANY, HEAD fallback, escaping. |
| `tests/RouteCollectionTest.php` | Ordering, copies, `findByName()`. |
| `tests/ApplicationTest.php` | Request/response conversion, groups, middleware order and short-circuit, error rendering, custom handler. |
| `tests/ResponseTest.php` | Response factories, body writing, immutability, `send()`. |
| `tests/ControllerTest.php` | Controller notations, context injection, repeated dispatch, invalid constructors, `view()` helper. |
| `tests/TemplateTest.php` | Rendering, escaping, partials, existence checks, traversal protection. |

## Testing an application without sending output

Use `Application::handle()` and inspect the `Response`:

```php
$app = Kurama\AppFactory::create(['debug' => true]);
$app->get('/users/{id}', fn (string $id) => Kurama\Response::json(['id' => $id]));

$response = $app->handle(new Kurama\Request('GET', '/users/42'));

self::assertSame(200, $response->getStatus());
self::assertSame(['id' => '42'], json_decode($response->getContent(), true));
```

If you test `run()` or `send()`, capture output with `ob_start()` / `ob_get_clean()` and reset `http_response_code(200)` in `tearDown()`.

## Adding a test

1. Put the test in the matching file under `tests/`.
2. Use `Kurama\Tests` namespace and extend `PHPUnit\Framework\TestCase`.
3. Use `declare(strict_types=1)`.
4. Run `composer test` before committing.

Data-driven tests use PHPUnit attributes:

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('cases')]
public function testSomething(string $input): void { /* ... */ }

public static function cases(): iterable
{
    yield 'case name' => ['value'];
}
```
