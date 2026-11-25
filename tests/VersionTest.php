<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testStableVersion(): void
    {
        self::assertSame('1.1.0', Version::CURRENT);
        self::assertSame(1, Version::MAJOR);
        self::assertSame(1, Version::MINOR);
        self::assertSame(0, Version::PATCH);
        self::assertSame('8.3', Version::PHP_MINIMUM);
    }
}
