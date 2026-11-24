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
 * instance, so partials and escaping are available without global helpers.
 */
final class Template
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $extension = '.php',
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $path = $this->path($template);

        if (!is_file($path)) {
            throw new RuntimeException("Template [{$template}] not found at {$path}.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
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
