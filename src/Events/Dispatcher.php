<?php

declare(strict_types=1);

namespace Kurama\Events;

/** Tiny event bus with priorities and wildcard listeners. */
final class Dispatcher
{
    /** @var array<string, list<array{0: callable, 1: int}>> */
    private array $listeners = [];

    /** @var list<array{0: string, 1: callable, 2: int}> */
    private array $wildcards = [];

    /** @var list<array{0: string, 1: list<mixed>}> */
    private array $queue = [];

    private bool $deferred = false;

    public function listen(string $event, callable $listener, int $priority = 0): self
    {
        $this->listeners[$event][] = [$listener, $priority];

        return $this;
    }

    public function once(string $event, callable $listener, int $priority = 0): self
    {
        $fired = false;
        $wrapped = static function (mixed ...$payload) use ($listener, &$fired): void {
            if ($fired) {
                return;
            }
            $fired = true;
            $listener(...$payload);
        };

        return $this->listen($event, $wrapped, $priority);
    }

    public function wildcard(string $pattern, callable $listener, int $priority = 0): self
    {
        $this->wildcards[] = [$pattern, $listener, $priority];

        return $this;
    }

    public function has(string $event): bool
    {
        if (isset($this->listeners[$event])) {
            return true;
        }

        foreach ($this->wildcards as [$pattern]) {
            if ($this->matches($pattern, $event)) {
                return true;
            }
        }

        return false;
    }

    public function forget(string $event): self
    {
        unset($this->listeners[$event]);
        $this->wildcards = array_values(array_filter(
            $this->wildcards,
            static fn (array $entry): bool => $entry[0] !== $event,
        ));

        return $this;
    }

    /** Queue events instead of running listeners immediately. */
    public function defer(bool $enabled = true): self
    {
        $this->deferred = $enabled;

        return $this;
    }

    public function isDeferred(): bool
    {
        return $this->deferred;
    }

    /** Add an event to the deferred queue. */
    public function queue(string $event, mixed ...$payload): self
    {
        $this->queue[] = [$event, $payload];

        return $this;
    }

    /** @return list<array{0: string, 1: list<mixed>}> */
    public function queued(): array
    {
        return $this->queue;
    }

    /** Dispatch all queued events in FIFO order. */
    public function flush(): void
    {
        while ($this->queue !== []) {
            [$event, $payload] = array_shift($this->queue);
            $this->dispatchNow($event, ...$payload);
        }
    }

    public function dispatch(string $event, mixed ...$payload): void
    {
        if ($this->deferred) {
            $this->queue($event, ...$payload);

            return;
        }

        $this->dispatchNow($event, ...$payload);
    }

    private function dispatchNow(string $event, mixed ...$payload): void
    {
        if (!$this->has($event)) {
            return;
        }

        $listeners = $this->listeners[$event] ?? [];
        foreach ($this->wildcards as [$pattern, $listener, $priority]) {
            if ($this->matches($pattern, $event)) {
                $listeners[] = [$listener, $priority];
            }
        }

        usort($listeners, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
        foreach ($listeners as [$listener]) {
            $listener(...$payload);
        }
    }

    private function matches(string $pattern, string $event): bool
    {
        if ($pattern === '*') {
            return true;
        }

        $regex = '~^' . implode('.*', array_map(
            static fn (string $part): string => preg_quote($part, '~'),
            explode('*', $pattern),
        )) . '$~';

        return @preg_match($regex, $event) === 1;
    }
}
