<?php

declare(strict_types=1);

namespace Kurama\Contracts;

use Kurama\Application;

interface ServiceProviderInterface
{
    /** Register bindings and services. Other providers may not be ready yet. */
    public function register(Application $app): void;

    /** Called after all providers have been registered. */
    public function boot(Application $app): void;
}
