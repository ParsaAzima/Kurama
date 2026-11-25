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
}
