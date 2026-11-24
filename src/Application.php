<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Exceptions\RouteNotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Optional application layer on top of the router.
 *
 * It owns the middleware pipeline, request/response conversion and error
 * rendering. The Router itself stays independent and side-effect free.
 */
class Application
{
    public readonly Router $router;

    /** @var list<callable> */
    private array $middleware = [];

    /** @var null|callable */
    private $errorHandler = null;

    /** @var array<string, ReflectionFunction|ReflectionMethod> */
    private array $reflectionCache = [];

    public function __construct(
        private readonly array $config = [],
        ?Router $router = null,
    ) {
        $this->router = $router ?? new Router();

        if (isset($config['timezone']) && is_string($config['timezone'])) {
            date_default_timezone_set($config['timezone']);
        }
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->router->get($pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->router->post($pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->router->put($pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->router->patch($pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->router->delete($pattern, $handler);
    }

    public function options(string $pattern, mixed $handler): Route
    {
        return $this->router->options($pattern, $handler);
    }

    public function head(string $pattern, mixed $handler): Route
    {
        return $this->router->head($pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): Route
    {
        return $this->router->any($pattern, $handler);
    }

    /** @param list<string>|string $methods */
    public function match(array|string $methods, string $pattern, mixed $handler): Route
    {
        return $this->router->match($methods, $pattern, $handler);
    }

    /**
     * Group routes under a prefix and/or with shared middleware.
     *
     * @param array{prefix?: string, middleware?: list<callable>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->router->group($attributes, function () use ($callback): void {
            $callback($this);
        });
    }

    public function middleware(callable ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /** Alias for Slim-style `$app->add($middleware)`. */
    public function add(callable ...$middleware): self
    {
        return $this->middleware(...$middleware);
    }

    /** Build a URL from a named route. */
    public function url(string $name, array $params = []): string
    {
        return $this->router->url($name, $params);
    }

    /** Alias for Slim-style `urlFor()`. */
    public function urlFor(string $name, array $params = []): string
    {
        return $this->url($name, $params);
    }

    /** Override default error rendering: fn (Throwable $e, Request $request) => Response|mixed */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    public function handle(Request $request): Response
    {
        try {
            $result = $this->router->resolve($request->method(), $request->path());

            if ($result === null) {
                throw new RouteNotFoundException($request->method(), $request->path());
            }

            $route = $result['route'];
            $parameters = $result['parameters'];
            $request = $request->withParams($parameters);

            $initialResponse = Response::html('');
            $action = function (Request $request) use ($route, $parameters, $initialResponse): mixed {
                $handler = $this->resolveHandler($route->handler());
                if (is_array($handler) && $handler[0] instanceof Controller) {
                    $handler[0]->setContext($request, $initialResponse);
                }

                $output = $this->invoke($handler, $parameters, $request, $initialResponse);

                return $output instanceof Response ? $output : Response::make($output);
            };

            $next = $action;
            $middleware = array_merge($this->middleware, $route->middlewares());
            foreach (array_reverse($middleware) as $item) {
                $previous = $next;
                $next = static fn (Request $request): mixed => $item($request, $previous);
            }

            $response = $next($request);

            return $response instanceof Response ? $response : Response::make($response);
        } catch (Throwable $exception) {
            return $this->renderError($exception, $request);
        }
    }

    public function run(?Request $request = null): void
    {
        $this->handle($request ?? Request::capture())->send();
    }

    /** Resolve class-string and Class@method handlers without a container. */
    private function resolveHandler(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
        }

        if (
            is_array($handler)
            && isset($handler[0], $handler[1])
            && is_string($handler[0])
            && class_exists($handler[0])
        ) {
            $reflection = new ReflectionClass($handler[0]);
            $constructor = $reflection->getConstructor();
            if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new InvalidArgumentException("{$handler[0]} needs constructor arguments; pass an instance instead.");
            }
            $handler = [$reflection->newInstance(), $handler[1]];
        }

        return is_callable($handler)
            ? $handler
            : throw new InvalidArgumentException('Invalid route handler.');
    }

    /** Cache reflection for named handlers; closures stay uncached. */
    private function reflectionFor(callable $handler): ReflectionFunction|ReflectionMethod
    {
        if ($handler instanceof \Closure) {
            return new ReflectionFunction($handler);
        }

        if (is_string($handler)) {
            return $this->reflectionCache['fn:' . $handler]
                ??= new ReflectionFunction($handler);
        }

        if (is_array($handler)) {
            $class = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
            $key = 'method:' . $class . '::' . $handler[1];

            return $this->reflectionCache[$key]
                ??= new ReflectionMethod($handler[0], $handler[1]);
        }

        $key = 'invoke:' . get_class($handler);

        return $this->reflectionCache[$key] ??= new ReflectionMethod($handler, '__invoke');
    }

    /**
     * Inject Slim-style arguments: Request, Response, $args and route params by name.
     */
    private function invoke(callable $handler, array $parameters, Request $request, Response $response): mixed
    {
        $reflection = $this->reflectionFor($handler);

        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($name === 'request' || ($type instanceof ReflectionNamedType && $type->getName() === Request::class)) {
                $arguments[] = $request;
            } elseif ($name === 'response' || ($type instanceof ReflectionNamedType && $type->getName() === Response::class)) {
                $arguments[] = $response;
            } elseif ($name === 'args') {
                $arguments[] = $parameters;
            } elseif (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new InvalidArgumentException("Cannot resolve argument \${$name}.");
            }
        }

        return $handler(...$arguments);
    }

    private function renderError(Throwable $exception, Request $request): Response
    {
        if ($this->errorHandler !== null) {
            $result = ($this->errorHandler)($exception, $request);

            return $result instanceof Response ? $result : Response::make($result);
        }

        $status = 500;
        $headers = [];

        if ($exception instanceof RouteNotFoundException) {
            $status = 404;
        } elseif ($exception instanceof MethodNotAllowedException) {
            $status = 405;
            $headers['Allow'] = implode(', ', $exception->allowedMethods());
        } elseif (!($this->config['debug'] ?? false)) {
            error_log(sprintf(
                '[Kurama] %s: %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
        }

        $message = $this->config['debug'] ?? false
            ? $exception->getMessage()
            : $this->statusText($status);

        $payload = ['error' => ['status' => $status, 'message' => $message]];

        if ($request->wantsJson() || str_starts_with($request->path(), '/api')) {
            return Response::json($payload, $status, $headers);
        }

        $title = htmlspecialchars($this->statusText($status), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>{$status} {$title}</title></head>"
            . "<body style=\"font-family:system-ui;max-width:40rem;margin:4rem auto;padding:0 1rem\">"
            . "<h1>{$status} {$title}</h1><p>{$text}</p><hr><small>Powered by Kurama</small></body></html>";

        return Response::html($html, $status, $headers);
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
