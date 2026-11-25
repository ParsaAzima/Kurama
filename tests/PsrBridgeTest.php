<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\AppFactory;
use Kurama\Bridge\PsrBridge;
use Kurama\Http\Response as PsrResponse;
use Kurama\Http\ResponseFactory;
use Kurama\Http\ServerRequest;
use Kurama\Request;
use Kurama\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class PsrBridgeTest extends TestCase
{
    public function testConvertsPsrServerRequestToKuramaRequest(): void
    {
        $psr = (new ServerRequest('GET', '/users/42?q=1'))
            ->withQueryParams(['q' => '1'])
            ->withCookieParams(['id' => '42'])
            ->withParsedBody(['name' => 'Kurama'])
            ->withHeader('Accept', 'application/json');

        $request = PsrBridge::toKuramaRequest($psr);

        self::assertSame('GET', $request->method());
        self::assertSame('/users/42', $request->uri);
        self::assertSame('1', $request->query('q'));
        self::assertSame('42', $request->cookie('id'));
        self::assertSame(['name' => 'Kurama'], $request->getParsedBody());
        self::assertSame('application/json', $request->getHeaderLine('accept'));
    }

    public function testApplicationCanHandlePsr7Request(): void
    {
        $app = AppFactory::create(['debug' => true, 'discovery.enabled' => false]);
        $app->get('/users/{id}', function (Request $request): Response {
            return Response::json(['id' => $request->param('id')]);
        });

        $response = $app->handlePsr7(new ServerRequest('GET', '/users/42'));

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"id":"42"}', (string) $response->getBody());
    }

    public function testConvertsKuramaResponseToPsrResponse(): void
    {
        $response = Response::json(['ok' => true], 201)
            ->withCookie(new \Kurama\Cookie('theme', 'dark'));

        $psr = PsrBridge::toPsrResponse($response, new ResponseFactory());

        self::assertInstanceOf(ResponseInterface::class, $psr);
        self::assertSame(201, $psr->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $psr->getHeaderLine('Content-Type'));
        self::assertStringContainsString('theme=dark', $psr->getHeaderLine('Set-Cookie'));
        self::assertSame('{"ok":true}', (string) $psr->getBody());
    }

    public function testConvertsPsrResponseBackToKuramaResponse(): void
    {
        $psr = (new PsrResponse(202))
            ->withHeader('X-Test', 'yes');
        $psr->getBody()->write('done');

        $response = PsrBridge::toKuramaResponse($psr);

        self::assertSame(202, $response->getStatus());
        self::assertSame('yes', $response->getHeader('X-Test'));
        self::assertSame('done', $response->getContent());
    }

    public function testPsr15AdapterRequiresInterfaces(): void
    {
        if (interface_exists(\Psr\Http\Server\MiddlewareInterface::class)) {
            self::markTestSkipped('PSR-15 interfaces are installed.');
        }

        $this->expectException(\InvalidArgumentException::class);
        PsrBridge::psr15Middleware(
            static fn ($request, $next) => Response::text('ok'),
            new ResponseFactory(),
        );
    }
}
