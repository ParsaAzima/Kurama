<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class CacheRoutesCommand extends Command
{
    public function name(): string
    {
        return 'cache:routes';
    }

    public function description(): string
    {
        return 'Write the route cache file';
    }

    public function handle(Input $input, Console $console): int
    {
        $app = $console->application();
        $app->cacheRoutes();
        $console->output('Route cache written.' . PHP_EOL);

        return 0;
    }
}
