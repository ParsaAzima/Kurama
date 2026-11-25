<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class ViewClearCommand extends Command
{
    public function name(): string
    {
        return 'view:clear';
    }

    public function description(): string
    {
        return 'Flush the rendered view cache';
    }

    public function handle(Input $input, Console $console): int
    {
        $console->application()->view->cache()?->flush();
        $console->output('View cache cleared.' . PHP_EOL);

        return 0;
    }
}
