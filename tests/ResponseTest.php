<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\JsonResponse;
use Kurama\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testTextAndHtmlFactories(): void
    {
        self::assertSame('text/plain; charset=UTF-8', Response::text('hi')->getHeader('Content-Type'));
        self::assertSame('text/html; charset=UTF-8', Response::html('<b>hi</b>')->getHeader('Content-Type'));
    }

    public function testJsonFactoryReturnsJsonResponse(): void
    {
        $response = Response::json(['name' => 'Kurama'], 201);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(201, $response->getStatus());
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertSame(['name' => 'Kurama'], json_decode($response->getContent(), true));
    }

    public function testMakeConvertsArraysToJson(): void
    {
        self::assertInstanceOf(JsonResponse::class, Response::make(['ok' => true]));
        self::assertSame('plain', Response::make('plain')->getContent());
    }

    public function testWithMethodsReturnCopies(): void
    {
        $original = Response::text('hi');
        $modified = $original->withStatus(201)->withHeader('X-Test', 'yes');

        self::assertSame(200, $original->getStatus());
        self::assertNull($original->getHeader('X-Test'));
        self::assertSame(201, $modified->getStatus());
        self::assertSame('yes', $modified->getHeader('X-Test'));
    }

    public function testRedirectFactory(): void
    {
        $response = Response::redirect('/home', 303);

        self::assertSame(303, $response->getStatus());
        self::assertSame('/home', $response->getHeader('Location'));
    }

    public function testSendEmitsHeadersAndBody(): void
    {
        $response = Response::text('sent');

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        self::assertSame('sent', $output);
        self::assertSame(200, http_response_code());
    }

    protected function tearDown(): void
    {
        http_response_code(200);
    }
}
