# CLI kernel

Kurama includes a small console kernel. Commands are classes, return an exit code and never call `exit()` themselves.

## Running

```bash
composer console -- list
composer console -- route:list
composer console -- help cache:routes
composer console -- version
# or directly:
php bin/kurama list
```

`bin/kurama` requires root `console.php`, which builds the application and registers demo routes. In your project, point that file at your own application.

## Built-in commands

| Command | Description |
| --- | --- |
| `list` | List available commands. |
| `help [command]` | Show help / command description. |
| `version` | Kurama and PHP versions. |
| `route:list` | List registered methods, patterns and names. |
| `cache:routes` | Write the route cache file. |
| `cache:clear` | Delete the route cache file. |

## Custom commands

```php
use Kurama\Console\Command;
use Kurama\Console\Console;
use Kurama\Console\Input;

final class GreetCommand extends Command
{
    public function name(): string { return 'greet'; }
    public function description(): string { return 'Greet someone'; }

    public function handle(Input $input, Console $console): int
    {
        $console->output('Hello ' . ($input->argument(0) ?? 'world') . PHP_EOL);

        return 0;
    }
}

$app->console()->command(new GreetCommand());
```

Then:

```bash
php bin/kurama greet Parsa --loud
```

## Input parsing

- The first non-option token is the command name.
- Following non-option tokens are positional arguments: `Input::arguments()`, `argument(0)`.
- `--name=value` sets an option; `--flag` is a boolean flag.
- `Input::option('name', 'default')`, `hasOption('flag')`, `options()`.

## Kernel API

| Method | Description |
| --- | --- |
| `$app->console` (public readonly `Console`) | Access the kernel. |
| `Application::runConsole(?array $argv = null): int` | Run and return an exit code. |
| `Console::command(Command\|string): self` | Register a command. |
| `Console::run(?array $argv = null): int` | Dispatch `$_SERVER['argv']` or explicit tokens. |
| `Console::output()` / `error()` | Write to stdout / stderr (injectable for tests). |

Passing explicit tokens makes the kernel easy to test; no global state is required.
