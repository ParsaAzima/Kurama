<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Small immutable HTTP response value object.
 */
class Response
{
    protected Body $body;

    /** @var list<Cookie> */
    private array $cookies = [];

    /** @param array<string, string> $headers */
    public function __construct(
        string|Body $content = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {
        $this->body = $content instanceof Body ? $content : new Body($content);
    }

    public function getBody(): Body
    {
        return $this->body;
    }

    /** Convert any handler result into a response. */
    public static function make(mixed $content = '', int $status = 200, array $headers = []): self
    {
        if (is_array($content) || $content instanceof \JsonSerializable || $content instanceof \stdClass) {
            return new JsonResponse($content, $status, $headers);
        }

        return new self((string) $content, $status, $headers);
    }

    public static function text(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, array_merge(['Content-Type' => 'text/plain; charset=UTF-8'], $headers));
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302, array $headers = []): self
    {
        return new self('', $status, array_merge(['Location' => $url], $headers));
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withCookie(Cookie $cookie): self
    {
        $clone = clone $this;
        $clone->cookies[] = $cookie;

        return $clone;
    }

    /** @return list<Cookie> */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function getContent(): string
    {
        return (string) $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie->toHeader(), false);
            }
        }

        echo $this->body;
    }
}
