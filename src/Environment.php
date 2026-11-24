<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Minimal .env loader. No dependency, no variable interpolation.
 */
final class Environment
{
    private array $values = [];

    public function __construct(?string $file = null, bool $overwrite = false)
    {
        if ($file !== null && is_file($file)) {
            $this->load($file, $overwrite);
        }
    }

    public function load(string $path, bool $overwrite = false): self
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $this;
        }

        foreach ($lines as $line) {
            $pair = $this->parseLine($line);
            if ($pair === null) {
                continue;
            }

            [$key, $value] = $pair;
            $exists = array_key_exists($key, $_ENV)
                || array_key_exists($key, $_SERVER)
                || getenv($key) !== false;

            if ($exists && !$overwrite) {
                continue;
            }

            $this->values[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function all(): array
    {
        return $this->values;
    }

    /** @return array{0: string, 1: string}|null */
    private function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        if (preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i', $line, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            $value = stripcslashes(substr($value, 1, -1));
        } elseif (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        } else {
            $value = (string) preg_replace('/\s+#.*$/', '', $value);
        }

        return [$matches[1], $value];
    }
}
