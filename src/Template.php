<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Minimal native-PHP template engine.
 *
 * Templates are plain PHP files. Inside a template, $this is the Template
 * instance, so partials, escaping and shared data are available without globals.
 */
final class Template
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly string $basePath,
        private readonly string $extension = '.php',
    ) {
    }

    /** Render a template, optionally wrapping it in a layout. */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Data shared by every render. Template data overrides shared values.
     */
    public function share(string $key, mixed $value): self
    {
        $this->shared[$key] = $value;

        return $this;
    }

    public function shared(): array
    {
        return $this->shared;
    }

    public function path(string $name): string
    {
        $this->assertValidName($name);

        return rtrim($this->basePath, '/\\') . '/'
            . str_replace('.', '/', $name) . $this->extension;
    }

    public function exists(string $name): bool
    {
        return is_file($this->path($name));
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Short alias for escape(). */
    public function e(string $value): string
    {
        return $this->escape($value);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $path = $this->path($template);

        if (!is_file($path)) {
            throw new RuntimeException("Template [{$template}] not found at {$path}.");
        }

        extract(array_merge($this->shared, $data), EXTR_SKIP);
        ob_start();
        try {
            require $path;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    }

    private function assertValidName(string $name): void
    {
        if (
            $name === ''
            || str_contains($name, '..')
            || preg_match('/^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/', $name) !== 1
        ) {
            throw new InvalidArgumentException("Invalid template name [{$name}].");
        }
    }
}
