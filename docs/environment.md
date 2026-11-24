# Environment variables

`Kurama\Environment` loads `.env` files without a dependency. It never performs variable interpolation.

## Auto-loading

`Application` loads `<project root>/.env` on construction when the file exists. Disable or relocate it in config:

```php
$app = AppFactory::create([
    'env.path' => __DIR__ . '/../config/.env',
    'env.enabled' => false,
]);
```

An example file is provided at `.env.example`.

## Syntax

```dotenv
# comment
APP_NAME=Kurama
APP_DEBUG=true
export APP_TIMEZONE=UTC
QUOTED="hello world"
LITERAL='no escapes here'
INLINE=value # trailing comment
```

Rules:

- Blank lines and `#` comments are ignored.
- `export ` prefix is optional.
- Double-quoted values process basic escapes via `stripcslashes()`.
- Single-quoted values are literal.
- Unquoted values lose trailing ` # comment`.

## Reading values

```php
$app->env('APP_NAME');                 // from loaded file or process env
$app->env('MISSING', 'fallback');

$env = $app->environment;
$env->get('APP_NAME');
$env->has('APP_NAME');
$env->all();
```

Loaded values are written to `$_ENV`, `$_SERVER` and `putenv()`, so `getenv('APP_NAME')` also works.

## Precedence and overwrite

By default, an existing process environment variable is **not** overwritten:

```php
$env = new Kurama\Environment();
$env->load($path);            // keeps existing values
$env->load($path, true);      // overwrites them
```

## Security

Do not commit real `.env` files. Keep secrets in the environment or in a git-ignored file, and add `.env` to your `.gitignore`.
