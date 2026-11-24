<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Tiny writable body, enough for the Slim-style `$response->getBody()->write()` API.
 * Not a PSR-7 stream implementation.
 */
final class Body
{
    private string $content;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function write(string $data): self
    {
        $this->content .= $data;

        return $this;
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
