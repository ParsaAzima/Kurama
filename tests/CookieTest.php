<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Cookie;
use Kurama\Response;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    public function testHeaderContainsAttributes(): void
    {
        $cookie = new Cookie('token', 'a b', time() + 60, '/', '', true, true, 'Strict');

        $header = $cookie->toHeader();

        self::assertStringContainsString('token=a%20b', $header);
        self::assertStringContainsString('Path=/', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('Expires=', $header);
    }

    public function testDeleteCookieIsExpired(): void
    {
        $header = Cookie::delete('token')->toHeader();

        self::assertStringContainsString('token=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
    }

    public function testResponseCarriesCookies(): void
    {
        $response = Response::text('ok')->withCookie(new Cookie('a', 'b'));

        self::assertCount(1, $response->getCookies());
        self::assertSame('a', $response->getCookies()[0]->name);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cookie('');
    }
}
