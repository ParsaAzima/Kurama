<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Thin session wrapper around $_SESSION. Native PHP sessions are lazy-started.
 */
final class Session
{
    private array $store = [];

    private bool $started = false;

    public function __construct(
        private readonly ?string $name = null,
        private readonly bool $native = true,
    ) {
    }

    /** Create a session backed by a plain array (useful for tests). */
    public static function fake(array &$store): self
    {
        $session = new self(null, false);
        $session->useStore($store);

        return $session;
    }

    public function useStore(array &$store): self
    {
        $this->store =& $store;
        $this->started = true;
        $this->ageFlash();

        return $this;
    }

    public function start(): self
    {
        if ($this->started) {
            return $this;
        }

        if ($this->native) {
            if ($this->name !== null) {
                session_name($this->name);
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
        }

        $_SESSION ??= [];
        $this->store =& $_SESSION;
        $this->started = true;
        $this->ageFlash();

        return $this;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function id(): string
    {
        $this->start();

        return $this->native ? session_id() : '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->start();
        $this->store[$key] = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->store);
    }

    public function remove(string $key): self
    {
        $this->start();
        unset($this->store[$key]);

        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->start();

        return array_diff_key($this->store, array_flip(['_flash', '_flash.old']));
    }

    public function flush(): self
    {
        $this->start();
        $this->store = [];

        return $this;
    }

    public function flash(string $key, mixed $value): self
    {
        $this->start();
        $this->store['_flash'][$key] = $value;

        return $this;
    }

    public function flashed(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $this->store['_flash.old'][$key] ?? $default;
        unset($this->store['_flash.old'][$key]);

        return $value;
    }

    public function regenerate(): self
    {
        $this->start();
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return $this;
    }

    public function destroy(): self
    {
        $this->start();
        $this->store = [];
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return $this;
    }

    private function ageFlash(): void
    {
        $this->store['_flash.old'] = $this->store['_flash'] ?? [];
        unset($this->store['_flash']);
    }
}
