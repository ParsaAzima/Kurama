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
| `render(string $template, array $data = [], ?string $layout = null): string` | Render to a string, optionally wrapping in a layout. |
| `share(string $key, mixed $value): self` | Add data shared by every render. |
| `shared(): array` | Shared data. |
| `path(string $template): string` | Resolve a template name to a file path. |
| `exists(string $template): bool` | Check whether the template file exists. |
| `escape(string $value): string` | HTML-escape a value. |
| `e(string $value): string` | Alias of `escape()`. |

## Layouts and shared data

```php
// views/layouts/main.php
<!doctype html><html><body><?= $content ?></body></html>

// Render content, then wrap it:
echo $this->render('home', $data, 'layouts.main');
```

The layout receives all template data plus a `content` variable containing the rendered inner template. `Application::render()` and `Controller::view()` accept an optional fourth layout argument.

```php
$app->template->share('appName', 'Kurama');
```

Shared data is merged before per-render data, so explicit data wins.

## Third-party engines

`Kurama\View\Renderer` dispatches by file extension. Register any callable or object with a `render()` method:

```php
$app = AppFactory::create([
    'view.engines' => [
        'twig' => fn (string $template, array $data): string =>
            $twig->render($template . '.twig', $data),
    ],
]);

$app->render('home.twig', ['title' => 'Kurama']);
```

To replace the default native engine entirely, set `view.engine` to a callable or a `RendererInterface` implementation. `CallableRenderer` adapts either form:

```php
use Kurama\View\CallableRenderer;

$renderer = new CallableRenderer($twig);
```

The engine receives the template name and data and returns a string. Layouts still work: the inner content is passed as the `content` variable to the layout template.

## Rendered-view cache

```php
$app = AppFactory::create([
    'view.cache.enabled' => true,
    'view.cache.path' => __DIR__ . '/../storage/views',
    'view.cache.ttl' => 3600,
]);
```

The cache key is the template name, layout name and a sorted hash of the data. `Renderer::render(..., $cached = false)` bypasses it for one call. Clear it with `composer console -- view:clear` or `$app->view->cache()?->flush()`.

## Safety and errors

- Template names may contain letters, digits, `_`, `.`, `-` and `/`; `..` is rejected, so `../secret` cannot escape the views directory.
- A missing template throws `RuntimeException`.
- An invalid template name throws `InvalidArgumentException`.
- Exceptions thrown inside a template are rethrown after the output buffer is cleaned, so `Application` can render them as a 500 response.
