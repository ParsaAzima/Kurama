<?php

declare(strict_types=1);

namespace Kurama;

/** A single HTTP cookie, emitted via Response::withCookie(). */
final class Cookie
{
    public function __construct(
        public readonly string $name,
        public readonly string $value = '',
        public readonly int $expires = 0,
        public readonly string $path = '/',
        public readonly string $domain = '',
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax',
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Cookie name must not be empty.');
        }
    }

    public static function delete(string $name, string $path = '/', string $domain = ''): self
    {
        return new self($name, '', time() - 3600, $path, $domain);
    }

    public function toHeader(): string
    {
        $parts = [rawurlencode($this->name) . '=' . rawurlencode($this->value)];

        if ($this->expires > 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s', $this->expires) . ' GMT';
            $parts[] = 'Max-Age=' . max(0, $this->expires - time());
        }
        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }
        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->sameSite !== '') {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        return implode('; ', $parts);
    }
}
