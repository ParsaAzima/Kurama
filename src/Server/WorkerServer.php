<?php

declare(strict_types=1);

namespace Kurama\Server;

use Kurama\Application;
use Throwable;

/**
 * Long-running HTTP worker with a cooperative stream_select event loop.
 *
 * When pcntl is available, --workers forks independent processes sharing the
 * listening socket. PHP has no safe threads in the default distribution, so
 * "threaded" here means multi-process concurrency plus non-blocking I/O.
 */
final class WorkerServer
{
    private bool $running = true;

    /** @var array<int, array{stream: resource, read: string, write: string, close: bool}> */
    private array $clients = [];

    public function __construct(
        private readonly Application $application,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8000,
        private readonly int $workers = 1,
    ) {
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(): int
    {
        $context = null;
        if (function_exists('pcntl_fork')) {
            $context = stream_context_create(['socket' => ['reuseport' => true]]);
        }

        $server = @stream_socket_server(
            'tcp://' . $this->host . ':' . $this->port,
            $errorNumber,
            $errorMessage,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );

        if ($server === false) {
            fwrite(STDERR, "Unable to listen on {$this->host}:{$this->port}: {$errorMessage}\n");

            return 1;
        }

        fwrite(STDOUT, "Kurama worker listening on http://{$this->host}:{$this->port}\n");

        $children = [];
        if ($this->workers > 1 && function_exists('pcntl_fork')) {
            for ($i = 1; $i < $this->workers; $i++) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    return $this->serve($server);
                }
                if ($pid > 0) {
                    $children[] = $pid;
                }
            }
        } elseif ($this->workers > 1) {
            fwrite(STDERR, "pcntl is unavailable; running a single worker process.\n");
        }

        $status = $this->serve($server);
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $childStatus);
        }
        fclose($server);

        return $status;
    }

    private function serve($server): int
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function (): void {
                $this->stop();
            });
            pcntl_signal(SIGTERM, function (): void {
                $this->stop();
            });
        }

        stream_set_blocking($server, false);

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $read = array_merge([$server], array_column($this->clients, 'stream'));
            $write = [];
            foreach ($this->clients as $client) {
                if ($client['write'] !== '') {
                    $write[] = $client['stream'];
                }
            }
            $except = null;

            if ($read === [] && $write === []) {
                break;
            }

            $ready = @stream_select($read, $write, $except, 1);
            if ($ready === false) {
                continue;
            }

            foreach ($read as $stream) {
                if ($stream === $server) {
                    $this->accept($server);
                    continue;
                }
                $this->readFrom((int) $stream, $stream);
            }

            foreach ($write as $stream) {
                $this->writeTo((int) $stream, $stream);
            }
        }

        foreach ($this->clients as $client) {
            fclose($client['stream']);
        }
        $this->clients = [];

        return 0;
    }

    private function accept($server): void
    {
        while (($client = @stream_socket_accept($server, 0)) !== false) {
            stream_set_blocking($client, false);
            $id = (int) $client;
            $this->clients[$id] = [
                'stream' => $client,
                'read' => '',
                'write' => '',
                'close' => false,
            ];
        }
    }

    /** @param resource $stream */
    private function readFrom(int $id, $stream): void
    {
        $chunk = fread($stream, 8192);
        if ($chunk === false || ($chunk === '' && feof($stream))) {
            $this->closeClient($id);

            return;
        }

        $this->clients[$id]['read'] .= $chunk;

        while (!$this->clients[$id]['close'] && $this->clients[$id]['read'] !== '') {
            $parsed = RequestParser::parse(
                $this->clients[$id]['read'],
                ['REMOTE_ADDR' => (string) stream_socket_get_name($stream, true)],
            );
            if ($parsed === null) {
                break;
            }

            $this->clients[$id]['read'] = substr($this->clients[$id]['read'], $parsed['consumed']);
            $request = $parsed['request'];

            $this->application->prepareForRequest();
            try {
                $response = $this->application->handle($request);
            } catch (Throwable $exception) {
                $response = $this->application->error($exception, $request);
            }

            $keepAlive = strtolower((string) $request->header('Connection', '')) !== 'close';
            $this->clients[$id]['write'] .= ResponseWriter::write($response, $keepAlive);
            if (!$keepAlive) {
                $this->clients[$id]['close'] = true;
            }
        }
    }

    /** @param resource $stream */
    private function writeTo(int $id, $stream): void
    {
        if (!isset($this->clients[$id]) || $this->clients[$id]['write'] === '') {
            return;
        }

        $written = fwrite($stream, $this->clients[$id]['write']);
        if ($written === false) {
            $this->closeClient($id);

            return;
        }

        $this->clients[$id]['write'] = substr($this->clients[$id]['write'], $written);
        if ($this->clients[$id]['write'] === '' && $this->clients[$id]['close']) {
            $this->closeClient($id);
        }
    }

    private function closeClient(int $id): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }
        fclose($this->clients[$id]['stream']);
        unset($this->clients[$id]);
    }
}
