<?php

declare(strict_types=1);

namespace Kurama\Exceptions;

use RuntimeException;

/** Thrown when the path exists but none of its routes accept the HTTP method. */
final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        private readonly array $allowedMethods,
        private readonly string $method = '',
        private readonly string $path = '',
    ) {
        $subject = $method !== '' ? $method . ' ' : '';
        $location = $path !== '' ? ' ' . $path : '';
        $allowed = implode(', ', $allowedMethods);

        parent::__construct(sprintf(
            'Method %snot allowed for%s. Allowed methods: %s',
            $subject,
            $location,
            $allowed !== '' ? $allowed : 'none',
        ));
    }

    /** @return list<string> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /** @return list<string> */
    public function allowed(): array
    {
        return $this->allowedMethods;
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
