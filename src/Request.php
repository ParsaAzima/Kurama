<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Small immutable value object representing the incoming HTTP request.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     * @param array<string, string> $params Matched route parameters.
     */
    public function __construct(
        public readonly string $method = 'GET',
        public readonly string $uri = '/',
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $server = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly array $params = [],
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return new self(
            method: $method,
            uri: $uri,
            query: $_GET ?? [],
            body: self::parseBody($method, $_SERVER),
            headers: self::captureHeaders($_SERVER),
            server: $_SERVER,
            cookies: $_COOKIE ?? [],
            files: $_FILES ?? [],
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper(trim($method));
    }

    /** Normalized path used by the router. */
    public function path(): string
    {
        return Router::normalizePath($this->uri);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace($this->query, $this->params, $this->body);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function wantsJson(): bool
    {
        return str_contains(strtolower((string) $this->header('Accept', '')), 'application/json')
            || strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /** @return Request A copy carrying matched route parameters. */
    public function withParams(array $params): self
    {
        return new self(
            $this->method,
            $this->uri,
            $this->query,
            $this->body,
            $this->headers,
            $this->server,
            $this->cookies,
            $this->files,
            $params,
        );
    }

    /** @return array<string, string> */
    private static function captureHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }

        return $headers;
    }

    /** @return array<string, mixed> */
    private static function parseBody(string $method, array $server): array
    {
        $contentType = strtolower((string) ($server['HTTP_CONTENT_TYPE'] ?? $server['CONTENT_TYPE'] ?? ''));
        $raw = (string) (file_get_contents('php://input') ?: '');

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($method === 'POST') {
            return $_POST ?? [];
        }

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true) && str_contains($contentType, 'x-www-form-urlencoded')) {
            parse_str($raw, $parsed);

            return $parsed;
        }

        return [];
    }
}
