<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Familiar Slim-style entry point: AppFactory::create()->run().
 */
final class AppFactory
{
    public static function create(array $config = []): Application
    {
        return new Application($config);
    }
}
