<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Dot-notation configuration repository.
 */
final class Config
{
    public function __construct(private array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target =& $this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target =& $target[$segment];
        }

        $target[array_shift($segments)] = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    public function load(array $items): void
    {
        $this->items = array_replace_recursive($this->items, $this->normalize($items));
    }

    /** Turn dotted keys into nested arrays so get() can read them. */
    private function normalize(array $items): array
    {
        $result = [];
        foreach ($items as $key => $value) {
            $value = is_array($value) ? $this->normalize($value) : $value;

            if (is_string($key) && str_contains($key, '.')) {
                $segments = explode('.', $key);
                $target =& $result;
                while (count($segments) > 1) {
                    $segment = array_shift($segments);
                    if (!isset($target[$segment]) || !is_array($target[$segment])) {
                        $target[$segment] = [];
                    }
                    $target =& $target[$segment];
                }
                $target[array_shift($segments)] = $value;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function all(): array
    {
        return $this->items;
    }
}
