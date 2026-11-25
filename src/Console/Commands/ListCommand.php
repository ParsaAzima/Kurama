<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class ListCommand extends Command
{
    public function name(): string
    {
        return 'list';
    }

    public function description(): string
    {
        return 'List available commands';
    }

    public function handle(Input $input, Console $console): int
    {
        $console->output('Kurama commands:' . PHP_EOL);
        foreach ($console->commands() as $name => $command) {
            $console->output(sprintf('  %-18s %s', $name, $command->description()) . PHP_EOL);
        }

        return 0;
    }
}
