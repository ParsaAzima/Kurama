<?php

declare(strict_types=1);

namespace Kurama\View;

use Kurama\Contracts\RendererInterface;

/** Dispatches rendering to native PHP or a registered third-party engine. */
final class Renderer
{
    /** @var array<string, RendererInterface> */
    private array $engines = [];

    public function __construct(
        private readonly RendererInterface $default,
        private readonly ?ViewCache $cache = null,
    ) {
    }

    public function engine(string $extension, RendererInterface $engine): self
    {
        $this->engines[ltrim(strtolower($extension), '.')] = $engine;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function render(
        string $template,
        array $data = [],
        ?string $layout = null,
        bool $cached = true,
    ): string {
        $key = $template . '|' . ($layout ?? '') . '|' . $this->dataHash($data);
        if ($cached && $this->cache !== null) {
            $hit = $this->cache->get($key);
            if ($hit !== null) {
                return $hit;
            }
        }

        $content = $this->forTemplate($template)->render($template, $data);
        if ($layout !== null) {
            $content = $this->forTemplate($layout)->render(
                $layout,
                array_merge($data, ['content' => $content]),
            );
        }

        if ($cached && $this->cache !== null) {
            $this->cache->put($key, $content);
        }

        return $content;
    }

    public function cache(): ?ViewCache
    {
        return $this->cache;
    }

    private function forTemplate(string $template): RendererInterface
    {
        $extension = strtolower((string) pathinfo($template, PATHINFO_EXTENSION));

        return $this->engines[$extension] ?? $this->default;
    }

    /** @param array<string, mixed> $data */
    private function dataHash(array $data): string
    {
        ksort($data);

        return hash('sha256', serialize($data));
    }
}
