<?php

declare(strict_types=1);

namespace Kurama\Console;

use Closure;
use Kurama\Application;
use Kurama\Console\Commands\CacheRoutesCommand;
use Kurama\Console\Commands\HelpCommand;
use Kurama\Console\Commands\ListCommand;
use Kurama\Console\Commands\RouteListCommand;
use Kurama\Console\Commands\VersionCommand;
use Throwable;

/** Minimal console kernel. Commands return exit codes; nothing calls exit(). */
final class Console
{
    /** @var array<string, Command> */
    private array $commands = [];

    private readonly Closure $output;

    private readonly Closure $error;

    public function __construct(
        private readonly Application $application,
        ?callable $output = null,
        ?callable $error = null,
    ) {
        $this->output = Closure::fromCallable($output ?? static function (string $message): void {
            if (defined('STDOUT')) {
                fwrite(STDOUT, $message);

                return;
            }
            echo $message;
        });
        $this->error = Closure::fromCallable($error ?? static function (string $message): void {
            if (defined('STDERR')) {
                fwrite(STDERR, $message);

                return;
            }
            echo $message;
        });

        foreach ([
            ListCommand::class,
            HelpCommand::class,
            VersionCommand::class,
            RouteListCommand::class,
            CacheRoutesCommand::class,
        ] as $command) {
            $this->command($command);
        }
    }

    public function application(): Application
    {
        return $this->application;
    }

    public function command(Command|string $command): self
    {
        $instance = is_string($command) ? $this->application->container->make($command) : $command;
        if (!$instance instanceof Command) {
            throw new \InvalidArgumentException('Invalid console command.');
        }

        $this->commands[$instance->name()] = $instance;

        return $this;
    }

    public function commandNamed(?string $name): ?Command
    {
        return $name === null ? null : ($this->commands[$name] ?? null);
    }

    /** @return array<string, Command> */
    public function commands(): array
    {
        return $this->commands;
    }

    public function output(string $message): void
    {
        ($this->output)($message);
    }

    public function error(string $message): void
    {
        ($this->error)($message);
    }

    /** @param list<string>|null $argv Tokens without the script name. */
    public function run(?array $argv = null): int
    {
        $input = $argv === null
            ? Input::fromArgv((array) ($_SERVER['argv'] ?? []))
            : new Input($argv);

        $name = $input->command() ?? 'list';
        $command = $this->commandNamed($name);

        if ($command === null) {
            $this->error("Unknown command [{$name}]." . PHP_EOL);

            return 1;
        }

        try {
            return $command->handle($input, $this);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage() . PHP_EOL);

            return 1;
        }
    }
}
