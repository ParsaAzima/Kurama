<?php

declare(strict_types=1);

namespace Kurama;

use Kurama\Contracts\ServiceProviderInterface;

/** Convenience base class: override register() and optionally boot(). */
abstract class ServiceProvider implements ServiceProviderInterface
{
    public function boot(Application $app): void
    {
    }

    /** @return list<class-string> Providers that must register before this one. */
    public function requires(): array
    {
        return [];
    }

    /** @return array<string, list<string>> Tag name => container ids to expose. */
    public function tags(): array
    {
        return [];
    }
}
