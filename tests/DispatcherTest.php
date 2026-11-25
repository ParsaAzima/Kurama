<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

final class DispatcherTest extends TestCase
{
    public function testListenersReceivePayloadInPriorityOrder(): void
    {
        $dispatcher = new Dispatcher();
        $seen = [];

        $dispatcher->listen('user.created', static function (int $id) use (&$seen): void {
            $seen[] = 'normal' . $id;
        });
        $dispatcher->listen('user.created', static function (int $id) use (&$seen): void {
            $seen[] = 'high' . $id;
        }, 10);

        $dispatcher->dispatch('user.created', 7);

        self::assertSame(['high7', 'normal7'], $seen);
    }

    public function testWildcardListenersMatchEventNames(): void
    {
        $dispatcher = new Dispatcher();
        $seen = [];

        $dispatcher->wildcard('user.*', static function (int $id) use (&$seen): void {
            $seen[] = $id;
        });

        self::assertTrue($dispatcher->has('user.deleted'));
        self::assertFalse($dispatcher->has('order.created'));

        $dispatcher->dispatch('user.deleted', 3);
        self::assertSame([3], $seen);
    }

    public function testOnceListenerRunsOnlyOnce(): void
    {
        $dispatcher = new Dispatcher();
        $count = 0;

        $dispatcher->once('ping', static function () use (&$count): void {
            $count++;
        });

        $dispatcher->dispatch('ping');
        $dispatcher->dispatch('ping');

        self::assertSame(1, $count);
    }

    public function testDeferredEventsAreQueuedAndFlushedInOrder(): void
    {
        $dispatcher = new Dispatcher();
        $seen = [];

        $dispatcher->listen('a', static function () use (&$seen): void {
            $seen[] = 'a';
        });
        $dispatcher->listen('b', static function () use (&$seen): void {
            $seen[] = 'b';
        });

        $dispatcher->defer();
        $dispatcher->dispatch('b');
        $dispatcher->dispatch('a');

        self::assertTrue($dispatcher->isDeferred());
        self::assertCount(2, $dispatcher->queued());
        self::assertSame([], $seen);

        $dispatcher->flush();

        self::assertSame(['b', 'a'], $seen);
        self::assertSame([], $dispatcher->queued());
    }

    public function testDispatchWithoutListenersIsSafeAndForgetWorks(): void
    {
        $dispatcher = new Dispatcher();
        $dispatcher->dispatch('nothing');

        $dispatcher->listen('thing', static fn () => null);
        self::assertTrue($dispatcher->has('thing'));

        $dispatcher->forget('thing');
        self::assertFalse($dispatcher->has('thing'));
    }
}
