<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class RouteListCommand extends Command
{
    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'List registered routes';
    }

    public function handle(Input $input, Console $console): int
    {
        $routes = $console->application()->router->routes();

        if ($routes->isEmpty()) {
            $console->output('No routes registered.' . PHP_EOL);

            return 0;
        }

        foreach ($routes->all() as $route) {
            $console->output(sprintf(
                '%-22s %-32s %s',
                implode('|', $route->methods()),
                $route->pattern(),
                $route->getName() ?? '-',
            ) . PHP_EOL);
        }

        return 0;
    }
}
