<?php

declare(strict_types=1);

namespace Kurama;

use Countable;

/**
 * Ordered collection of registered routes.
 *
 * The internal array is never exposed by reference, so retrieving routes
 * cannot mutate the collection or unrelated entries.
 */
final class RouteCollection implements Countable
{
    /** @var list<Route> */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /** @return list<Route> */
    public function all(): array
    {
        return $this->routes;
    }

    public function get(int $index): ?Route
    {
        return $this->routes[$index] ?? null;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function isEmpty(): bool
    {
        return $this->routes === [];
    }
}
