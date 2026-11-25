<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class HelpCommand extends Command
{
    public function name(): string
    {
        return 'help';
    }

    public function description(): string
    {
        return 'Show help for a command';
    }

    public function handle(Input $input, Console $console): int
    {
        $name = $input->argument(0);
        $command = $name !== null ? $console->commandNamed($name) : null;

        if ($name !== null && $command === null) {
            $console->error("Unknown command [{$name}]." . PHP_EOL);

            return 1;
        }

        if ($command === null) {
            return $console->run(new Input(['list']));
        }

        $console->output(sprintf('%s - %s', $command->name(), $command->description()) . PHP_EOL);

        return 0;
    }
}
