<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Route;
use Kurama\RouteCollection;
use PHPUnit\Framework\TestCase;

final class RouteCollectionTest extends TestCase
{
    public function testNewCollectionIsEmpty(): void
    {
        $collection = new RouteCollection();

        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
        self::assertNull($collection->get(0));
    }

    public function testRoutesAreStoredInRegistrationOrder(): void
    {
        $first = new Route(['GET'], '/first', static fn () => null);
        $second = new Route(['POST'], '/second', static fn () => null);

        $collection = new RouteCollection();
        $collection->add($first);
        $collection->add($second);

        self::assertFalse($collection->isEmpty());
        self::assertCount(2, $collection);
        self::assertSame([$first, $second], $collection->all());
        self::assertSame($first, $collection->get(0));
        self::assertSame($second, $collection->get(1));
        self::assertNull($collection->get(2));
    }

    public function testFindByName(): void
    {
        $named = new Route(['GET'], '/users/{id}', static fn () => null);
        $named->name('user.show');

        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/', static fn () => null));
        $collection->add($named);

        self::assertSame($named, $collection->findByName('user.show'));
        self::assertNull($collection->findByName('missing'));
    }

    public function testReturnedArrayCannotMutateTheCollection(): void
    {
        $route = new Route(['GET'], '/users', static fn () => null);
        $collection = new RouteCollection();
        $collection->add($route);

        $routes = $collection->all();
        array_pop($routes);

        self::assertCount(1, $collection);
        self::assertSame($route, $collection->get(0));
    }

    public function testAddingRouteDoesNotMutatePreviouslyRetrievedRoutes(): void
    {
        $first = new Route(['GET'], '/first', static fn () => null);
        $second = new Route(['GET'], '/second', static fn () => null);
        $collection = new RouteCollection();
        $collection->add($first);

        $snapshot = $collection->all();
        $collection->add($second);

        self::assertSame([$first], $snapshot);
        self::assertSame([$first, $second], $collection->all());
    }
}
