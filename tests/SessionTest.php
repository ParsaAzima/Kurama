<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testReadsAndWritesBackingStore(): void
    {
        $store = ['user' => 'kurama'];
        $session = Session::fake($store);

        self::assertSame('kurama', $session->get('user'));
        self::assertTrue($session->has('user'));
        self::assertSame('fallback', $session->get('missing', 'fallback'));

        $session->set('theme', 'dark');
        self::assertSame('dark', $store['theme']);

        $session->remove('user');
        self::assertFalse($session->has('user'));
    }

    public function testAllExcludesInternalFlashKeys(): void
    {
        $store = [];
        $session = Session::fake($store);
        $session->set('a', 1);
        $session->flash('message', 'hello');

        self::assertSame(['a' => 1], $session->all());
    }

    public function testFlashIsAvailableOnNextSession(): void
    {
        $store = [];
        $first = Session::fake($store);
        $first->flash('message', 'saved');

        self::assertNull($first->flashed('message'));

        $second = Session::fake($store);
        self::assertSame('saved', $second->flashed('message'));
        self::assertNull($second->flashed('message'));
    }

    public function testFlushClearsEverything(): void
    {
        $store = ['a' => 1];
        $session = Session::fake($store);
        $session->flush();

        self::assertSame([], $session->all());
        self::assertSame([], $store);
    }
}
