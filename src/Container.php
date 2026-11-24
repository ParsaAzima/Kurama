<?php

declare(strict_types=1);

namespace Kurama;

use Closure;
use Kurama\Exceptions\ContainerException;
use Kurama\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Small PSR-11-style container with auto-wiring and cached resolution plans.
 */
final class Container
{
    /** @var array<string, array{concrete: Closure|string, singleton: bool}> */
    private array $bindings = [];

    private array $instances = [];

    private array $resolved = [];

    private array $building = [];

    /** @var array<string, ReflectionFunction|ReflectionMethod> */
    private array $reflectionCache = [];

    /** @var array<string, list<array{name: string, class: ?string, hasDefault: bool, default: mixed}>> */
    private array $callPlans = [];

    /** @var array<string, array{reflection: ReflectionClass, instantiable: bool, params: list<array{name: string, class: ?string, hasDefault: bool, default: mixed}>}> */
    private array $buildPlans = [];

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
        unset($this->resolved[$id]);
    }

    public function bind(string $id, Closure|string|null $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$id] = [
            'concrete' => $concrete ?? $id,
            'singleton' => $singleton,
        ];
        unset($this->resolved[$id]);
    }

    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    public function factory(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, false);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->bindings[$id])
            || class_exists($id)
            || interface_exists($id);
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException($id);
        }

        return $this->resolve($id, []);
    }

    public function make(string $id, array $parameters = []): mixed
    {
        return $this->resolve($id, $parameters);
    }

    /** Invoke a callable using a cached argument plan. */
    public function call(callable $callback, array $parameters = []): mixed
    {
        $arguments = [];

        foreach ($this->callPlan($callback) as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            if ($parameter['class'] !== null && $this->has($parameter['class'])) {
                $arguments[] = $this->resolve($parameter['class'], []);
                continue;
            }

            if ($parameter['hasDefault']) {
                $arguments[] = $parameter['default'];
                continue;
            }

            throw new ContainerException("Cannot resolve argument \${$name}.");
        }

        return $callback(...$arguments);
    }

    private function resolve(string $id, array $parameters): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (isset($this->building[$id])) {
            throw new ContainerException("Circular dependency detected for [{$id}].");
        }

        $binding = $this->bindings[$id] ?? null;
        $concrete = $binding['concrete'] ?? $id;
        $this->building[$id] = true;

        try {
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } elseif (is_string($concrete)) {
                $object = $this->build($concrete, $parameters);
            } else {
                throw new ContainerException("Invalid binding for [{$id}].");
            }
        } finally {
            unset($this->building[$id]);
        }

        if ($binding['singleton'] ?? false) {
            $this->resolved[$id] = $object;
        }

        return $object;
    }

    private function build(string $class, array $parameters): object
    {
        if (!class_exists($class)) {
            throw new NotFoundException($class);
        }

        $plan = $this->buildPlans[$class] ??= $this->buildPlan($class);
        if (!$plan['instantiable']) {
            throw new ContainerException("[{$class}] is not instantiable.");
        }

        if ($plan['params'] === []) {
            return $plan['reflection']->newInstance();
        }

        $arguments = [];
        foreach ($plan['params'] as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            if ($parameter['class'] !== null) {
                $arguments[] = $this->resolve($parameter['class'], []);
                continue;
            }

            if ($parameter['hasDefault']) {
                $arguments[] = $parameter['default'];
                continue;
            }

            throw new ContainerException("Cannot resolve dependency \${$name} in [{$class}].");
        }

        return $plan['reflection']->newInstanceArgs($arguments);
    }

    /** @return array{reflection: ReflectionClass, instantiable: bool, params: list<array{name: string, class: ?string, hasDefault: bool, default: mixed}>} */
    private function buildPlan(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        return [
            'reflection' => $reflection,
            'instantiable' => $reflection->isInstantiable(),
            'params' => $constructor === null ? [] : $this->parameterPlan($constructor),
        ];
    }

    /** @return list<array{name: string, class: ?string, hasDefault: bool, default: mixed}> */
    private function callPlan(callable $callback): array
    {
        $key = $this->cacheKey($callback);
        if ($key === null) {
            return $this->parameterPlan(new ReflectionFunction($callback));
        }

        return $this->callPlans[$key] ??= $this->parameterPlan($this->reflectionFor($callback));
    }

    /** @return list<array{name: string, class: ?string, hasDefault: bool, default: mixed}> */
    private function parameterPlan(ReflectionFunction|ReflectionMethod $reflection): array
    {
        $plan = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $plan[] = [
                'name' => $parameter->getName(),
                'class' => $type instanceof ReflectionNamedType && !$type->isBuiltin()
                    ? $type->getName()
                    : null,
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $plan;
    }

    private function reflectionFor(callable $callback): ReflectionFunction|ReflectionMethod
    {
        if ($handler = $this->cacheKey($callback)) {
            return $this->reflectionCache[$handler]
                ??= match (true) {
                    is_string($callback) => new ReflectionFunction($callback),
                    is_array($callback) => new ReflectionMethod($callback[0], $callback[1]),
                    default => new ReflectionMethod($callback, '__invoke'),
                };
        }

        return new ReflectionFunction($callback);
    }

    private function cacheKey(callable $callback): ?string
    {
        if ($callback instanceof Closure) {
            return null;
        }

        if (is_string($callback)) {
            return 'fn:' . $callback;
        }

        if (is_array($callback)) {
            $class = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];

            return 'method:' . $class . '::' . $callback[1];
        }

        return 'invoke:' . get_class($callback);
    }
}
