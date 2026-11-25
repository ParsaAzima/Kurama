<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;

/**
 * A single registered route: HTTP methods, path pattern, handler and metadata.
 *
 * Placeholders use {name} or {name:constraint}, e.g. /users/{id:\d+}.
 */
final class Route
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'ANY'];

    private const PLACEHOLDER = '/\{([A-Za-z_][A-Za-z0-9_]*)(\?)?(?::((?:[^{}]|\{[^{}]*\})*))?\}/';

    private readonly string $pattern;

    /** @var list<string> */
    private readonly array $methods;

    private readonly mixed $handler;

    /** @var list<string> */
    private readonly array $parameterNames;

    /** @var array<string, string> */
    private readonly array $constraints;

    private readonly string $regex;

    private readonly bool $hasParameters;

    private readonly bool $hasOptional;

    private readonly int $slashes;

    private ?string $routeName = null;

    /** @var list<callable> */
    private array $middleware = [];

    /**
     * @param list<string> $methods
     * @param mixed        $handler Any PHP callable.
     */
    public function __construct(array $methods, string $pattern, mixed $handler)
    {
        $this->methods = self::normalizeMethods($methods);

        self::validateHandler($handler);

        $this->handler = $handler;
        $this->pattern = self::normalizePattern($pattern);
        $this->regex = $this->compilePattern();
    }

    public function middleware(callable ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /** Alias for Slim-style `$route->add($middleware)`. */
    public function add(callable ...$middleware): self
    {
        return $this->middleware(...$middleware);
    }

    /** @return list<callable> */
    public function middlewares(): array
    {
        return $this->middleware;
    }

    public function name(string $name): self
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid route name "' . $name . '".');
        }

        $this->routeName = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->routeName;
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

    /** @return array<string, string> */
    public function constraints(): array
    {
        return $this->constraints;
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
        // Static routes are the common case: no regex needed.
        if (!$this->hasParameters) {
            return $this->pattern === $path ? [] : null;
        }

        // Cheap rejection before PCRE: a parameter cannot add separators.
        if (!$this->hasOptional && substr_count($path, '/') !== $this->slashes) {
            return null;
        }

        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            if (!isset($matches[$name]) || $matches[$name] === '') {
                continue;
            }
            $parameters[$name] = rawurldecode($matches[$name]);
        }

        return $parameters;
    }

    /**
     * Build a concrete URI, validating each value against its constraint.
     *
     * @param array<string, mixed> $params
     */
    public function uri(array $params = []): string
    {
        $uri = preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use ($params): string {
                $name = $match[1];
                $optional = ($match[2] ?? '') === '?';
                if (!array_key_exists($name, $params) || $params[$name] === null || $params[$name] === '') {
                    if ($optional) {
                        return '';
                    }
                    throw new InvalidArgumentException("Missing route parameter [{$name}].");
                }

                $value = (string) $params[$name];
                $constraint = $this->constraints[$name] ?? '[^/]+';
                if (@preg_match('~^(?:' . $constraint . ')$~D', $value) !== 1) {
                    throw new InvalidArgumentException(
                        "Value [{$value}] does not match the constraint for [{$name}].",
                    );
                }

                return rawurlencode($value);
            },
            $this->pattern,
        );

        $uri = (string) preg_replace('~/+~', '/', $uri);

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /** Accept callables, [Controller::class, 'method'] and 'Controller@method'. */
    private static function validateHandler(mixed $handler): void
    {
        if (is_callable($handler)) {
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (class_exists($class) && method_exists($class, $method)) {
                return;
            }
        }

        if (
            is_array($handler)
            && isset($handler[0], $handler[1])
            && is_string($handler[0])
            && class_exists($handler[0])
            && method_exists($handler[0], $handler[1])
        ) {
            return;
        }

        throw new InvalidArgumentException('Route handler must be a callable.');
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

        if (str_contains($pattern, '#') || preg_match('/\s/', $pattern) === 1) {
            throw new InvalidArgumentException('Route pattern must not contain fragments or whitespace.');
        }

        preg_match_all(self::PLACEHOLDER, $pattern, $tokens, PREG_OFFSET_CAPTURE);
        $literal = '';
        $cursor = 0;
        foreach ($tokens[0] as [$text, $position]) {
            $literal .= substr($pattern, $cursor, $position - $cursor);
            $cursor = $position + strlen($text);
        }
        $literal .= substr($pattern, $cursor);
        if (str_contains($literal, '?')) {
            throw new InvalidArgumentException('Route pattern must not contain a query string.');
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
        preg_match_all(self::PLACEHOLDER, $pattern, $matches, PREG_OFFSET_CAPTURE);

        // Any brace left outside a placeholder means the pattern is malformed.
        $literalParts = '';
        $cursor = 0;
        foreach ($matches[0] as [$text, $position]) {
            $literalParts .= substr($pattern, $cursor, $position - $cursor);
            $cursor = $position + strlen($text);
        }
        $literalParts .= substr($pattern, $cursor);
        if (str_contains($literalParts, '{') || str_contains($literalParts, '}')) {
            throw new InvalidArgumentException('Malformed route placeholder in "' . $pattern . '".');
        }

        $regex = '';
        $offset = 0;
        $previousEnd = null;
        $hasOptional = false;
        $names = [];
        $constraints = [];

        foreach ($matches[0] as $index => $token) {
            [$text, $position] = $token;
            $name = $matches[1][$index][0];
            $optional = ($matches[2][$index][0] ?? '') === '?';
            $constraint = $matches[3][$index][0] ?? '';

            if (str_contains($text, ':') && $constraint === '') {
                throw new InvalidArgumentException("Empty constraint for [{$name}].");
            }

            if (isset($names[$name])) {
                throw new InvalidArgumentException('Duplicate route parameter "{' . $name . '}".');
            }

            if ($previousEnd === $position) {
                throw new InvalidArgumentException('Adjacent route placeholders are ambiguous.');
            }

            if ($constraint !== '') {
                self::validateConstraint($constraint, $name);
            }

            $expression = $constraint !== '' ? '(?:' . $constraint . ')' : '[^/]+';
            $before = substr($pattern, $offset, $position - $offset);

            if ($optional && str_ends_with($before, '/')) {
                $regex .= preg_quote(substr($before, 0, -1), '~');
                $regex .= '(?:/(?P<' . $name . '>' . $expression . '))?';
            } elseif ($optional) {
                $regex .= preg_quote($before, '~');
                $regex .= '(?P<' . $name . '>' . $expression . ')?';
            } else {
                $regex .= preg_quote($before, '~');
                $regex .= '(?P<' . $name . '>' . $expression . ')';
            }

            if ($optional) {
                $hasOptional = true;
            }
            $names[$name] = true;
            $constraints[$name] = $constraint !== '' ? $constraint : '[^/]+';
            $previousEnd = $position + strlen($text);
            $offset = $previousEnd;
        }

        $regex .= preg_quote(substr($pattern, $offset), '~');
        $this->parameterNames = array_keys($names);
        $this->constraints = $constraints;
        $this->hasParameters = $names !== [];
        $this->hasOptional = $hasOptional;
        $this->slashes = substr_count($this->pattern, '/');

        return '~^' . $regex . '$~D';
    }

    private static function validateConstraint(string $constraint, string $name): void
    {
        if (str_contains($constraint, '~')) {
            throw new InvalidArgumentException("Constraint for [{$name}] must not contain '~'.");
        }

        if (preg_match('/\\\\[0-9]/', $constraint) === 1) {
            throw new InvalidArgumentException("Backreferences are not allowed in constraint for [{$name}].");
        }

        if (@preg_match('~^(?:' . $constraint . ')$~D', '') === false) {
            throw new InvalidArgumentException("Invalid constraint for [{$name}].");
        }

        if (@preg_match('~^(?:' . $constraint . ')$~D', '') === 1) {
            throw new InvalidArgumentException("Constraint for [{$name}] must not match an empty string.");
        }

        // A parameter can never consume a URL separator.
        if (
            @preg_match('~^(?:' . $constraint . ')$~D', '/') === 1
            || @preg_match('~^(?:' . $constraint . ')$~D', 'a/b') === 1
        ) {
            throw new InvalidArgumentException("Constraint for [{$name}] must not match '/'.");
        }
    }
}
