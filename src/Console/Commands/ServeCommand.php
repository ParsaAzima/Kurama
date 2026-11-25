<?php

declare(strict_types=1);

namespace Kurama\Console\Commands;

use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;
use Kurama\Server\WorkerServer;

final class ServeCommand extends Command
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'Run the long-running Kurama worker server';
    }

    public function handle(Input $input, Console $console): int
    {
        $app = $console->application();
        $host = (string) ($input->option('host') ?: $app->config('server.host', '127.0.0.1'));
        $port = (int) ($input->option('port') ?: $app->config('server.port', 8000));
        $workers = (int) ($input->option('workers') ?: $app->config('server.workers', 1));

        $server = new WorkerServer($app, $host, $port, $workers);

        return $server->run();
    }
}
