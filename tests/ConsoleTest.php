<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\AppFactory;
use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private function console(): array
    {
        $capture = new ConsoleCapture();
        $app = AppFactory::create(['debug' => true, 'discovery.enabled' => false]);
        $console = new Console(
            $app,
            static function (string $message) use ($capture): void {
                $capture->output .= $message;
            },
            static function (string $message) use ($capture): void {
                $capture->error .= $message;
            },
        );

        return [$console, $capture];
    }

    public function testListAndVersionCommands(): void
    {
        [$console, $capture] = $this->console();

        self::assertSame(0, $console->run(['list']));
        self::assertStringContainsString('route:list', $capture->output);

        self::assertSame(0, $console->run(['version']));
        self::assertStringContainsString(PHP_VERSION, $capture->output);
    }

    public function testHelpForKnownAndUnknownCommands(): void
    {
        [$console, $capture] = $this->console();

        self::assertSame(0, $console->run(['help', 'route:list']));
        self::assertStringContainsString('List registered routes', $capture->output);

        self::assertSame(1, $console->run(['help', 'missing']));
        self::assertStringContainsString('Unknown command', $capture->error);
    }

    public function testUnknownCommandReturnsExitCodeOne(): void
    {
        [$console, $capture] = $this->console();

        self::assertSame(1, $console->run(['nope']));
        self::assertStringContainsString('Unknown command [nope]', $capture->error);
    }

    public function testCustomCommandReceivesInput(): void
    {
        [$console, $capture] = $this->console();
        $console->command(new GreetCommand());

        self::assertSame(0, $console->run(['greet', 'Parsa', '--loud']));
        self::assertStringContainsString('HELLO PARSA', $capture->output);
    }

    public function testCacheClearCommandRemovesFile(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-clear-' . bin2hex(random_bytes(4));
        mkdir($directory, 0775, true);
        $path = $directory . '/routes.php';
        file_put_contents($path, '<?php return [];');

        $capture = new ConsoleCapture();
        $app = AppFactory::create([
            'debug' => true,
            'discovery.enabled' => false,
            'route.cache.path' => $path,
        ]);
        $console = new Console(
            $app,
            static function (string $message) use ($capture): void {
                $capture->output .= $message;
            },
            static function (string $message) use ($capture): void {
                $capture->error .= $message;
            },
        );

        try {
            self::assertSame(0, $console->run(['cache:clear']));
            self::assertFileDoesNotExist($path);
            self::assertStringContainsString('cleared', $capture->output);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testInputParsesOptionsAndArguments(): void
    {
        $input = new Input(['greet', 'Parsa', '--name=Ali', '--verbose', 'extra']);

        self::assertSame('greet', $input->command());
        self::assertSame(['Parsa', 'extra'], $input->arguments());
        self::assertSame('Ali', $input->option('name'));
        self::assertTrue($input->hasOption('verbose'));
    }
}

final class ConsoleCapture
{
    public string $output = '';

    public string $error = '';
}

final class GreetCommand extends Command
{
    public function name(): string
    {
        return 'greet';
    }

    public function description(): string
    {
        return 'Greet someone';
    }

    public function handle(Input $input, Console $console): int
    {
        $name = $input->argument(0) ?? 'world';
        $greeting = 'Hello ' . $name;
        $console->output(($input->hasOption('loud') ? strtoupper($greeting) : $greeting) . PHP_EOL);

        return 0;
    }
}
