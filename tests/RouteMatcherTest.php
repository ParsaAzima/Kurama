<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Route;
use Kurama\RouteCollection;
use Kurama\RouteMatcher;
use PHPUnit\Framework\TestCase;

final class RouteMatcherTest extends TestCase
{
    public function testExactStaticMatch(): void
    {
        $handler = static fn (): string => 'about';
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/about', $handler));

        $result = (new RouteMatcher())->match($collection, 'GET', '/about');

        self::assertNotNull($result);
        self::assertSame($handler, $result['route']->handler());
        self::assertSame([], $result['parameters']);
    }

    public function testDynamicMatchReturnsOrderedParameters(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/posts/{post}/comments/{comment}', static fn () => null));

        $result = (new RouteMatcher())->match($collection, 'GET', '/posts/1/comments/2');

        self::assertNotNull($result);
        self::assertSame(['post' => '1', 'comment' => '2'], $result['parameters']);
    }

    public function testEncodedParameterIsDecoded(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/files/{name}', static fn () => null));

        $result = (new RouteMatcher())->match($collection, 'GET', '/files/a%2Fb%20c');

        self::assertNotNull($result);
        self::assertSame(['name' => 'a/b c'], $result['parameters']);
    }

    public function testNoPathMatchReturnsNull(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/about', static fn () => null));

        self::assertNull((new RouteMatcher())->match($collection, 'GET', '/contact'));
    }

    public function testAllowedMethodsAreCollectedForSamePath(): void
    {
        $getHandler = static fn (): string => 'get';
        $postHandler = static fn (): string => 'post';

        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/users', $getHandler));
        $collection->add(new Route(['POST'], '/users', $postHandler));
        $collection->add(new Route(['GET'], '/other', static fn () => null));

        $matcher = new RouteMatcher();

        self::assertSame($getHandler, $matcher->match($collection, 'GET', '/users')['route']->handler());
        self::assertSame($postHandler, $matcher->match($collection, 'POST', '/users')['route']->handler());

        try {
            $matcher->match($collection, 'PUT', '/users');
            self::fail('Expected MethodNotAllowedException.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET', 'POST', 'HEAD'], $exception->allowedMethods());
        }
    }

    public function testAnyRouteMatchesEveryMethod(): void
    {
        $collection = new RouteCollection();
        $collection->add(new Route(['ANY'], '/health', static fn () => null));

        $matcher = new RouteMatcher();

        self::assertNotNull($matcher->match($collection, 'GET', '/health'));
        self::assertNotNull($matcher->match($collection, 'DELETE', '/health'));
    }

    public function testHeadFallsBackToGet(): void
    {
        $handler = static fn (): string => 'list';
        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/users', $handler));

        $result = (new RouteMatcher())->match($collection, 'HEAD', '/users');

        self::assertNotNull($result);
        self::assertSame($handler, $result['route']->handler());
    }

    public function testFirstMatchingRouteWins(): void
    {
        $first = static fn (): string => 'first';
        $second = static fn (): string => 'second';

        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/users/{id}', $first));
        $collection->add(new Route(['GET'], '/users/{id}', $second));

        $result = (new RouteMatcher())->match($collection, 'GET', '/users/42');

        self::assertNotNull($result);
        self::assertSame($first, $result['route']->handler());
    }

    public function testStaticSegmentsAreEscaped(): void
    {
        $dotHandler = static fn (): string => 'dot route';
        $dynamicHandler = static fn (): string => 'dynamic';

        $collection = new RouteCollection();
        $collection->add(new Route(['GET'], '/files/report.v1', $dotHandler));
        $collection->add(new Route(['GET'], '/files/{name}', $dynamicHandler));

        $result = (new RouteMatcher())->match($collection, 'GET', '/files/reportXv1');

        self::assertNotNull($result);
        self::assertSame($dynamicHandler, $result['route']->handler());
        self::assertSame(['name' => 'reportXv1'], $result['parameters']);
    }
}
