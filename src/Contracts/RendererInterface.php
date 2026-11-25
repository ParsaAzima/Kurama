<?php

declare(strict_types=1);

namespace Kurama\Contracts;

interface RendererInterface
{
    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string;
}
