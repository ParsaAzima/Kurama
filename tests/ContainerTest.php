<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Config;
use Kurama\Container;
use Kurama\Exceptions\ContainerException;
use Kurama\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testInstanceAndGet(): void
    {
        $container = new Container();
        $value = new stdClass();
        $container->instance('thing', $value);

        self::assertTrue($container->has('thing'));
        self::assertSame($value, $container->get('thing'));
    }

    public function testMissingEntryThrows(): void
    {
        $this->expectException(NotFoundException::class);
        (new Container())->get('missing');
    }

    public function testAutoWiring(): void
    {
        $container = new Container();
        $container->instance(Config::class, new Config(['app' => ['name' => 'Kurama']]));

        $service = $container->make(ContainerService::class);

        self::assertSame('Kurama', $service->name());
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $container->singleton('thing', static fn (): stdClass => new stdClass());

        self::assertSame($container->get('thing'), $container->get('thing'));
    }

    public function testFactoryCreatesNewInstances(): void
    {
        $container = new Container();
        $container->factory('thing', static fn (): stdClass => new stdClass());

        self::assertNotSame($container->get('thing'), $container->get('thing'));
    }

    public function testCallResolvesTypesAndNamedParameters(): void
    {
        $container = new Container();
        $container->instance(Config::class, new Config(['app' => ['name' => 'Kurama']]));

        $result = $container->call(
            static function (Config $config, string $suffix): string {
                return $config->get('app.name') . ':' . $suffix;
            },
            ['suffix' => 'ok'],
        );

        self::assertSame('Kurama:ok', $result);
    }

    public function testCircularDependencyThrows(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->make(CircularA::class);
    }
}

final class ContainerService
{
    public function __construct(private readonly Config $config)
    {
    }

    public function name(): string
    {
        return (string) $this->config->get('app.name');
    }
}

final class CircularA
{
    public function __construct(private readonly CircularB $b)
    {
    }
}

final class CircularB
{
    public function __construct(private readonly CircularA $a)
    {
    }
}
