<?php

declare(strict_types=1);

namespace Kurama\Exceptions;

use RuntimeException;

/** Thrown when no registered path matches the requested URI. */
final class RouteNotFoundException extends RuntimeException
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
    ) {
        parent::__construct(sprintf('No route found for %s %s.', $method, $path));
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }
}
