<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;

/**
 * A single registered route: HTTP methods, path pattern and handler.
 *
 * Placeholders use the {name} syntax and may only contain URL-safe
 * characters (no URL separator "/").
 */
final class Route
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'ANY'];

    private readonly string $pattern;

    /** @var list<string> */
    private readonly array $methods;

    private readonly mixed $handler;

    /** @var list<string> */
    private readonly array $parameterNames;

    private readonly string $regex;

    /**
     * @param list<string> $methods
     * @param mixed        $handler Any PHP callable.
     */
    public function __construct(array $methods, string $pattern, mixed $handler)
    {
        $this->methods = self::normalizeMethods($methods);

        if (!is_callable($handler)) {
            throw new InvalidArgumentException('Route handler must be a callable.');
        }

        $this->handler = $handler;
        $this->pattern = self::normalizePattern($pattern);
        $this->regex = $this->compilePattern();
    }

    /** @var list<callable> */
    private array $middleware = [];

    public function middleware(callable ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /** @return list<callable> */
    public function middlewares(): array
    {
        return $this->middleware;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    /** @return list<string> */
    public function parameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Whether this route accepts the given HTTP method.
     *
     * ANY accepts every method. HEAD is implicitly accepted by GET routes.
     */
    public function supports(string $method): bool
    {
        $method = strtoupper(trim($method));

        if ($this->methods === ['ANY'] || in_array($method, $this->methods, true)) {
            return true;
        }

        return $method === 'HEAD' && in_array('GET', $this->methods, true);
    }

    /**
     * Match the path and return decoded parameters in declaration order.
     *
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            $parameters[$name] = rawurldecode($matches[$name]);
        }

        return $parameters;
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    private static function normalizeMethods(array $methods): array
    {
        if ($methods === []) {
            throw new InvalidArgumentException('A route must declare at least one HTTP method.');
        }

        $normalized = [];
        foreach ($methods as $method) {
            if (!is_string($method) || !in_array(strtoupper(trim($method)), self::METHODS, true)) {
                throw new InvalidArgumentException('Unsupported HTTP method in route definition.');
            }
            $normalized[] = strtoupper(trim($method));
        }

        return in_array('ANY', $normalized, true) ? ['ANY'] : array_values(array_unique($normalized));
    }

    private static function normalizePattern(string $pattern): string
    {
        if ($pattern === '' || $pattern[0] !== '/') {
            throw new InvalidArgumentException('Route pattern must begin with "/".');
        }

        if (str_contains($pattern, '?') || str_contains($pattern, '#') || preg_match('/\s/', $pattern) === 1) {
            throw new InvalidArgumentException('Route pattern must not contain query strings, fragments or whitespace.');
        }

        if ($pattern !== '/' && str_contains($pattern, '//')) {
            throw new InvalidArgumentException('Route pattern must not contain empty segments.');
        }

        // Trailing slash policy: "/users/" and "/users" are the same route.
        $normalized = $pattern === '/' ? '/' : rtrim($pattern, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    /** Build a safe regex: static parts escaped, placeholders limited to one segment. */
    private function compilePattern(): string
    {
        $pattern = $this->pattern;
        preg_match_all('/\{([^{}]*)\}/', $pattern, $matches, PREG_OFFSET_CAPTURE);

        if (
            substr_count($pattern, '{') !== count($matches[0])
            || substr_count($pattern, '}') !== count($matches[0])
        ) {
            throw new InvalidArgumentException('Malformed route placeholder in "' . $pattern . '".');
        }

        $regex = '';
        $offset = 0;
        $previousEnd = null;
        $names = [];

        foreach ($matches[0] as $index => $token) {
            [$text, $position] = $token;
            $name = $matches[1][$index][0];

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException('Invalid route parameter name in "' . $pattern . '".');
            }

            if (isset($names[$name])) {
                throw new InvalidArgumentException('Duplicate route parameter "{' . $name . '}".');
            }

            if ($previousEnd === $position) {
                throw new InvalidArgumentException('Adjacent route placeholders are ambiguous.');
            }

            $regex .= preg_quote(substr($pattern, $offset, $position - $offset), '~');
            $regex .= '(?P<' . $name . '>[^/]+)';

            $names[$name] = true;
            $previousEnd = $position + strlen($text);
            $offset = $previousEnd;
        }

        $regex .= preg_quote(substr($pattern, $offset), '~');
        $this->parameterNames = array_keys($names);

        return '~^' . $regex . '$~D';
    }
}
