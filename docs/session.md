# Session

`Kurama\Session` is a thin wrapper over native PHP sessions. Sessions are started lazily on first access.

## Configuration

```php
$app = AppFactory::create([
    'session.name' => 'kurama_session',
    'session.native' => true,
]);
```

Set `session.native` to `false` when you manage `$_SESSION` yourself.

## API

```php
$session = $app->session;

$session->start();
$session->id();

$session->set('user', 42);
$session->get('user', null);
$session->has('user');
$session->remove('user');
$session->all();
$session->flush();

$session->flash('message', 'Saved!');
$session->flashed('message');

$session->regenerate();
$session->destroy();
```

`all()` excludes internal flash keys. `start()` is idempotent.

## Flash data

A flash value is written for the **next** request:

```php
$session->flash('message', 'Saved!'); // current request
$session->flashed('message');        // null now, value on the next request
```

Internally, `_flash` is moved to `_flash.old` at the start of each session. `flashed()` consumes the value once.

## Testing

Use a plain array instead of native PHP sessions:

```php
$store = [];
$session = Kurama\Session::fake($store);
$session->set('user', 'kurama');
```

The array is passed by reference, so you can inspect or seed it directly. The same instance is also available as `$app->container->get('session')`.
