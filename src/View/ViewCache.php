<?php

declare(strict_types=1);

namespace Kurama\View;

/** File-backed rendered-view cache with TTL. */
final class ViewCache
{
    private string $directory;

    public function __construct(
        string $path,
        private readonly int $ttl = 0,
    ) {
        $this->directory = rtrim($path, '/\\');
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key): ?string
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }

        if ($this->ttl > 0 && filemtime($file) + $this->ttl < time()) {
            @unlink($file);

            return null;
        }

        $contents = file_get_contents($file);

        return $contents === false ? null : $contents;
    }

    public function put(string $key, string $value): void
    {
        @file_put_contents($this->file($key), $value, LOCK_EX);
    }

    public function forget(string $key): void
    {
        $file = $this->file($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function flush(): void
    {
        foreach (glob($this->directory . '/*.view') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function file(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.view';
    }
}
