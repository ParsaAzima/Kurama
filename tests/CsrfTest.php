<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Csrf;
use Kurama\Exceptions\CsrfTokenMismatchException;
use Kurama\Request;
use Kurama\Response;
use Kurama\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsStableAndValidated(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));
        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('wrong'));
        self::assertFalse($csrf->validate(null));
    }

    public function testFieldRendersHiddenInput(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));

        self::assertStringContainsString('name="_token"', $csrf->field());
        self::assertStringContainsString($csrf->token(), $csrf->field());
    }

    public function testValidateRequestFromBodyAndHeader(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));
        $token = $csrf->token();

        self::assertTrue($csrf->validateRequest(new Request('POST', '/x', body: ['_token' => $token])));
        self::assertTrue($csrf->validateRequest(new Request('POST', '/x', headers: ['X-CSRF-TOKEN' => $token])));
        self::assertFalse($csrf->validateRequest(new Request('POST', '/x')));
    }

    public function testMiddlewareSkipsSafeMethods(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));
        $middleware = $csrf->middleware();
        $next = static fn (Request $request): Response => Response::text('ok');

        self::assertSame('ok', $middleware(new Request('GET', '/x'), $next)->getContent());
    }

    public function testMiddlewareRejectsUnsafeMethodWithoutToken(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));
        $middleware = $csrf->middleware();
        $next = static fn (Request $request): Response => Response::text('ok');

        $this->expectException(CsrfTokenMismatchException::class);
        $middleware(new Request('POST', '/x'), $next);
    }

    public function testRegenerateChangesToken(): void
    {
        $store = [];
        $csrf = new Csrf(Session::fake($store));
        $before = $csrf->token();
        $csrf->regenerate();

        self::assertNotSame($before, $csrf->token());
    }
}
