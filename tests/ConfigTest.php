<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDotNotationGetAndSet(): void
    {
        $config = new Config(['app' => ['name' => 'Kurama']]);

        self::assertSame('Kurama', $config->get('app.name'));
        self::assertTrue($config->has('app.name'));
        self::assertNull($config->get('app.missing'));
        self::assertSame('fallback', $config->get('app.missing', 'fallback'));

        $config->set('views.path', '/tmp/views');
        self::assertSame('/tmp/views', $config->get('views.path'));
        self::assertSame(['path' => '/tmp/views'], $config->get('views'));
    }

    public function testLoadNormalizesDottedKeys(): void
    {
        $config = new Config();
        $config->load(['debug' => true, 'views.path' => '/views', 'app' => ['name' => 'Kurama']]);

        self::assertTrue($config->get('debug'));
        self::assertSame('/views', $config->get('views.path'));
        self::assertSame('Kurama', $config->get('app.name'));
    }

    public function testLoadMergesRecursively(): void
    {
        $config = new Config(['app' => ['name' => 'Kurama', 'env' => 'local']]);
        $config->load(['app' => ['env' => 'prod']]);

        self::assertSame(
            ['name' => 'Kurama', 'env' => 'prod'],
            $config->get('app'),
        );
    }

    public function testAllReturnsFullArray(): void
    {
        $config = new Config(['debug' => true]);

        self::assertSame(['debug' => true], $config->all());
    }
}
