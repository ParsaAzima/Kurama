# Templates

Kurama includes a tiny native-PHP template engine. There is no Twig/Blade dependency and no new runtime package.

## Configuration

```php
$app = AppFactory::create([
    'views.path' => __DIR__ . '/../views',
]);
```

If `views.path` is not set, Kurama uses `<project root>/views`. The project root is the directory passed as the third argument to `Application::__construct()` (or the package root by default).

## Rendering from a route

```php
$app->get('/', fn (): Response => $app->render('home', [
    'title' => 'Kurama',
    'name' => 'world',
]));
```

`Application::render()` returns an HTML `Response` with status 200 unless you pass a different status as the third argument.

## Rendering from a controller

```php
final class PageController extends Controller
{
    public function show(string $name): Response
    {
        return $this->view('home', [
            'title' => 'My page',
            'name' => $this->param('name'),
        ]);
    }
}
```

The template engine is injected into the controller context automatically.

## Template files

Templates are plain PHP files with a `.php` extension. Names use dot notation for subdirectories:

```
views/
├── home.php
└── partials/
    └── greeting.php
```

```php
<?= $this->render('partials.greeting', ['name' => 'Kurama']) ?>
```

Inside a template, `$this` is the `Kurama\Template` instance, so partials and escaping are available without global functions. Data is extracted into local variables with `EXTR_SKIP`.

## Escaping

```php
<?= $this->escape($name) ?>
<?= $this->e($name) ?>  <!-- short alias -->
```

Always escape user-provided values. `escape()` uses `htmlspecialchars()` with `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8.

## Template API

| Method | Description |
| --- | --- |
| `render(string $template, array $data = []): string` | Render to a string. |
| `path(string $template): string` | Resolve a template name to a file path. |
| `exists(string $template): bool` | Check whether the template file exists. |
| `escape(string $value): string` | HTML-escape a value. |
| `e(string $value): string` | Alias of `escape()`. |

## Safety and errors

- Template names may contain letters, digits, `_`, `.`, `-` and `/`; `..` is rejected, so `../secret` cannot escape the views directory.
- A missing template throws `RuntimeException`.
- An invalid template name throws `InvalidArgumentException`.
- Exceptions thrown inside a template are rethrown after the output buffer is cleaned, so `Application` can render them as a 500 response.
