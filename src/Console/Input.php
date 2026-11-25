<?php

declare(strict_types=1);

namespace Kurama\Console;

/** Parsed console arguments: command, positional args and long options. */
final class Input
{
    /** @var list<string> */
    private array $positional = [];

    /** @var array<string, string|bool> */
    private array $options = [];

    /** @param list<string> $tokens */
    public function __construct(array $tokens)
    {
        for ($i = 0; $i < count($tokens); $i++) {
            $token = (string) $tokens[$i];

            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                if (str_contains($body, '=')) {
                    [$name, $value] = explode('=', $body, 2);
                    $this->options[$name] = $value;
                } else {
                    $this->options[$body] = true;
                }

                continue;
            }

            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $this->options[substr($token, 1)] = true;
                continue;
            }

            $this->positional[] = $token;
        }
    }

    /** @param list<string> $argv */
    public static function fromArgv(array $argv): self
    {
        array_shift($argv);

        return new self(array_values($argv));
    }

    public function command(): ?string
    {
        return $this->positional[0] ?? null;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return array_slice($this->positional, 1);
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments()[$index] ?? $default;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /** @return array<string, string|bool> */
    public function options(): array
    {
        return $this->options;
    }
}
