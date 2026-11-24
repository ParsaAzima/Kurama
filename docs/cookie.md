# Cookies

`Kurama\Cookie` describes a cookie; `Response::withCookie()` attaches it.

## Creating cookies

```php
use Kurama\Cookie;
use Kurama\Response;

$cookie = new Cookie(
    name: 'theme',
    value: 'dark',
    expires: time() + 86400,
    path: '/',
    domain: '',
    secure: true,
    httpOnly: true,
    sameSite: 'Lax',
);

return Response::text('ok')->withCookie($cookie);
```

Defaults: `path=/`, `HttpOnly`, `SameSite=Lax`, no expiry (session cookie). Name and value are encoded with `rawurlencode()` in the header.

## Deleting

```php
return Response::text('ok')->withCookie(Cookie::delete('theme'));
```

This emits an expired cookie with `Max-Age=0`.

## Reading

```php
$request->cookie('theme', 'light');
```

Incoming cookies come from `Request::capture()` / `$_COOKIE`. Outgoing cookies are stored on the response and emitted as separate `Set-Cookie` headers by `send()`.

## API

`Cookie::__construct(...)`, `Cookie::delete(...)`, `toHeader()`, `Response::withCookie()`, `Response::getCookies()`, `Request::cookie()`.
