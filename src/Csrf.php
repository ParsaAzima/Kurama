<?php

declare(strict_types=1);

namespace Kurama;

use Closure;
use Kurama\Exceptions\CsrfTokenMismatchException;

/** CSRF tokens stored in the session, with a ready-made middleware. */
final class Csrf
{
    public function __construct(
        private readonly Session $session,
        private readonly string $key = '_csrf_token',
    ) {
    }

    public function token(): string
    {
        $this->session->start();
        $token = $this->session->get($this->key);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set($this->key, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function validate(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->token(), $token);
    }

    public function validateRequest(Request $request): bool
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

        return $this->validate(is_string($token) ? $token : null);
    }

    public function regenerate(): self
    {
        $this->session->set($this->key, bin2hex(random_bytes(32)));

        return $this;
    }

    /** Middleware: skips safe methods and rejects unsafe ones without a token. */
    public function middleware(): Closure
    {
        $csrf = $this;

        return static function (Request $request, callable $next) use ($csrf) {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return $next($request);
            }

            if (!$csrf->validateRequest($request)) {
                throw new CsrfTokenMismatchException();
            }

            return $next($request);
        };
    }
}
