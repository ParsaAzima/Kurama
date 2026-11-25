<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class CacheClearCommand extends Command
{
    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Delete the route cache file';
    }

    public function handle(Input $input, Console $console): int
    {
        $app = $console->application();
        $path = (string) $app->config(
            'route.cache.path',
            dirname(__DIR__, 3) . '/storage/cache/routes.php',
        );

        if (is_file($path) && !@unlink($path)) {
            $console->error("Unable to delete [{$path}]." . PHP_EOL);

            return 1;
        }

        $console->output('Route cache cleared.' . PHP_EOL);

        return 0;
    }
}
