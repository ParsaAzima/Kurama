<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Exceptions\RouteNotFoundException;
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

            $action = function (Request $request) use ($route, $parameters): mixed {
                $handler = $route->handler();
                $output = $this->invoke($handler, $parameters, $request);

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

    /** Inject route parameters by name, plus Request when asked for. */
    private function invoke(callable $handler, array $parameters, Request $request): mixed
    {
        $reflection = match (true) {
            $handler instanceof \Closure, is_string($handler) => new ReflectionFunction($handler),
            is_array($handler) => new ReflectionMethod($handler[0], $handler[1]),
            default => new ReflectionMethod($handler, '__invoke'),
        };

        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if ($name === 'request' || ($type instanceof ReflectionNamedType && $type->getName() === Request::class)) {
                $arguments[] = $request;
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
