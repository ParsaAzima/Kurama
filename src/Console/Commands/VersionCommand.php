<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;
use Kurama\Version;

final class VersionCommand extends Command
{
    public function name(): string
    {
        return 'version';
    }

    public function description(): string
    {
        return 'Show Kurama and PHP versions';
    }

    public function handle(Input $input, Console $console): int
    {
        $console->output(
            'Kurama ' . Version::CURRENT . ' by berlium / PHP ' . PHP_VERSION . PHP_EOL,
        );

        return 0;
    }
}
