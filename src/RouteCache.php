<?php

declare(strict_types=1);

namespace Kurama;

use Closure;
use InvalidArgumentException;

/**
 * Export/import route definitions to a plain PHP file.
 *
 * Closures and anonymous classes cannot be cached. Use class@method,
 * [Class::class, 'method'] or function names for cacheable routes.
 */
final class RouteCache
{
    public static function write(Router $router, string $path, ?Container $container = null): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $export = '<?php' . PHP_EOL . 'return ' . var_export(self::export($router), true) . ';' . PHP_EOL;
        if (@file_put_contents($path, $export, LOCK_EX) === false) {
            throw new InvalidArgumentException("Unable to write route cache to [{$path}].");
        }
    }

    /** @return list<array<string, mixed>> */
    public static function export(Router $router): array
    {
        $routes = [];

        foreach ($router->routes()->all() as $route) {
            $routes[] = [
                'methods' => $route->methods(),
                'pattern' => $route->pattern(),
                'handler' => self::describe($route->handler()),
                'name' => $route->getName(),
                'middleware' => array_map(self::describe(...), $route->middlewares()),
            ];
        }

        return $routes;
    }

    public static function load(string $path, ?Router $router = null, ?Container $container = null): Router
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Route cache [{$path}] not found.");
        }

        $definitions = require $path;
        if (!is_array($definitions)) {
            throw new InvalidArgumentException('Route cache must return an array.');
        }

        $router ??= new Router();
        foreach ($definitions as $definition) {
            $route = $router->match(
                $definition['methods'],
                $definition['pattern'],
                self::resolve($definition['handler'], $container),
            );

            if (!empty($definition['name'])) {
                $route->name($definition['name']);
            }

            foreach ($definition['middleware'] ?? [] as $middleware) {
                $route->middleware(self::resolve($middleware, $container));
            }
        }

        return $router;
    }

    /** @return array<string, mixed> */
    private static function describe(mixed $callable): array
    {
        if ($callable instanceof Closure) {
            throw new InvalidArgumentException('Closures cannot be cached; use a class method or function name.');
        }

        if (is_string($callable)) {
            if (str_contains($callable, '@')) {
                [$class, $method] = explode('@', $callable, 2);

                return self::classDescriptor($class, $method);
            }
            if (function_exists($callable)) {
                return ['type' => 'function', 'value' => $callable];
            }
            if (class_exists($callable)) {
                return ['type' => 'invoke', 'class' => $callable];
            }
        }

        if (is_array($callable) && isset($callable[0], $callable[1])) {
            $target = $callable[0];
            $class = is_object($target) ? get_class($target) : (is_string($target) ? $target : '');

            return self::classDescriptor($class, (string) $callable[1]);
        }

        if (is_object($callable)) {
            return ['type' => 'invoke', 'class' => get_class($callable)];
        }

        throw new InvalidArgumentException('Unsupported handler in route cache.');
    }

    /** @return array{type: string, class: string, method?: string} */
    private static function classDescriptor(string $class, string $method): array
    {
        if ($class === '' || str_contains($class, "\0") || !class_exists($class)) {
            throw new InvalidArgumentException("Class [{$class}] cannot be cached.");
        }

        return ['type' => 'class', 'class' => $class, 'method' => $method];
    }

    /** @param array<string, mixed> $descriptor */
    private static function resolve(array $descriptor, ?Container $container): callable
    {
        $make = static fn (string $class): object => $container !== null
            ? $container->make($class)
            : new $class();

        return match ($descriptor['type']) {
            'function' => $descriptor['value'],
            'class' => [$make($descriptor['class']), $descriptor['method']],
            'invoke' => $make($descriptor['class']),
            default => throw new InvalidArgumentException('Unknown cached handler type.'),
        };
    }
}
