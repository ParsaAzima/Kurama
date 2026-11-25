<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Cookie;
use Kurama\Response;
use Kurama\Server\RequestParser;
use Kurama\Server\ResponseWriter;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    public function testParsesGetRequestWithQueryAndCookies(): void
    {
        $raw = "GET /users/42?q=hello HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Cookie: theme=dark; id=7\r\n"
            . "\r\n";

        $parsed = RequestParser::parse($raw, ['REMOTE_ADDR' => '127.0.0.1']);

        self::assertNotNull($parsed);
        self::assertSame('GET', $parsed['request']->method());
        self::assertSame('/users/42', $parsed['request']->uri);
        self::assertSame('hello', $parsed['request']->query('q'));
        self::assertSame('dark', $parsed['request']->cookie('theme'));
        self::assertSame(strlen($raw), $parsed['consumed']);
    }

    public function testParsesJsonBodyOnlyWhenComplete(): void
    {
        $head = "POST /echo HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: 7\r\n\r\n";
        self::assertNull(RequestParser::parse($head . '{"a":'));

        $parsed = RequestParser::parse($head . '{"a":1}');
        self::assertNotNull($parsed);
        self::assertSame(['a' => 1], $parsed['request']->getParsedBody());
    }

    public function testResponseWriterEmitsHeadersAndCookies(): void
    {
        $response = Response::json(['ok' => true], 201)
            ->withCookie(new Cookie('theme', 'dark'));

        $raw = ResponseWriter::write($response);

        self::assertStringStartsWith('HTTP/1.1 201 Created', $raw);
        self::assertStringContainsString('Content-Length: 11', $raw);
        self::assertStringContainsString('Connection: keep-alive', $raw);
        self::assertStringContainsString('Set-Cookie: theme=dark', $raw);
        self::assertStringEndsWith('{"ok":true}', $raw);
    }
}
