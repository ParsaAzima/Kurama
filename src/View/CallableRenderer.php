<?php

declare(strict_types=1);

namespace Kurama\View;

use Kurama\Contracts\RendererInterface;

/** Adapts any callable or object with render() to RendererInterface. */
final class CallableRenderer implements RendererInterface
{
    private mixed $callable;

    public function __construct(mixed $renderer)
    {
        if (is_callable($renderer)) {
            $this->callable = $renderer;

            return;
        }

        if (is_object($renderer) && method_exists($renderer, 'render')) {
            $this->callable = [$renderer, 'render'];

            return;
        }

        throw new \InvalidArgumentException('Renderer must be callable or expose a render() method.');
    }

    public function render(string $template, array $data = []): string
    {
        return (string) ($this->callable)($template, $data);
    }
}
