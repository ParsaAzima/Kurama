# CSRF protection

`Kurama\Csrf` stores a random token in the session and validates unsafe requests.

## Token and field

```php
$token = $app->csrf->token();
$field = $app->csrf->field(); // <input type="hidden" name="_token" value="...">
```

A token is generated once and reused until `regenerate()` is called.

## Middleware

```php
$app->middleware($app->csrf->middleware());
```

Safe methods (`GET`, `HEAD`, `OPTIONS`) pass through. Other methods must provide the token either as `_token` in the request body or as an `X-CSRF-TOKEN` header. A mismatch throws `CsrfTokenMismatchException`, rendered as **419 Page Expired**.

```php
$app->csrf->validateRequest($request); // bool
$app->csrf->validate($token);          // bool
$app->csrf->regenerate();              // rotate token
```

## Notes

- The session is started automatically when the token is first needed.
- Tokens are compared with `hash_equals()` to avoid timing leaks.
- CSRF is opt-in: Kurama does not add the middleware for you.
- For JSON clients, send `X-CSRF-TOKEN` from the value in your page or response.
