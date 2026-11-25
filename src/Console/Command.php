<?php

declare(strict_types=1);

namespace Kurama\Console;

abstract class Command
{
    abstract public function name(): string;

    public function description(): string
    {
        return '';
    }

    abstract public function handle(Input $input, Console $console): int;
}
