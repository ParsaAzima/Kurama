<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\AppFactory;
use Kurama\Bridge\PsrBridge;
use Kurama\Request;
use Kurama\Response;
use PHPUnit\Framework\TestCase;

final class PsrBridgeTest extends TestCase
{
    public function testConvertsPsrRequestToKuramaRequest(): void
    {
        $request = PsrBridge::toKuramaRequest(new FakePsrRequest());

        self::assertSame('GET', $request->method());
        self::assertSame('/users/42?q=1', $request->uri);
        self::assertSame(['page' => '1'], $request->getQueryParams());
        self::assertSame(['name' => 'Kurama'], $request->getParsedBody());
        self::assertSame('application/json', $request->getHeaderLine('accept'));
        self::assertSame('42', $request->cookie('id'));
    }

    public function testApplicationCanHandlePsrRequestAndReturnPsrResponse(): void
    {
        $app = AppFactory::create(['debug' => true, 'discovery.enabled' => false]);
        $app->get('/users/{id}', function (Request $request, Response $response): Response {
            return Response::json(['id' => $request->param('id')], 201);
        });

        $psr = $app->handlePsr(new FakePsrRequest(), new FakeResponseFactory());

        self::assertSame(201, $psr->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $psr->getHeaderLine('Content-Type'));
        self::assertSame('{"id":"42"}', (string) $psr->getBody());
    }

    public function testRejectsNonPsrObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PsrBridge::toKuramaRequest(new \stdClass());
    }
}

final class FakePsrRequest
{
    public function getMethod(): string
    {
        return 'GET';
    }

    public function getUri(): object
    {
        return new FakeUri('/users/42?q=1');
    }

    public function getHeaders(): array
    {
        return ['Accept' => ['application/json']];
    }

    public function getQueryParams(): array
    {
        return ['page' => '1'];
    }

    public function getParsedBody(): object
    {
        return (object) ['name' => 'Kurama'];
    }

    public function getServerParams(): array
    {
        return ['REMOTE_ADDR' => '10.0.0.2'];
    }

    public function getCookieParams(): array
    {
        return ['id' => '42'];
    }

    public function getUploadedFiles(): array
    {
        return [];
    }
}

final class FakeUri
{
    public function __construct(private readonly string $uri)
    {
    }

    public function __toString(): string
    {
        return $this->uri;
    }
}

final class FakeResponseFactory
{
    public function createResponse(int $status): FakePsrResponse
    {
        return new FakePsrResponse($status);
    }
}

final class FakePsrResponse
{
    private array $headers = [];

    private FakeStream $body;

    public function __construct(private readonly int $status)
    {
        $this->body = new FakeStream();
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeaderLine(string $name): string
    {
        return $this->headers[$name] ?? '';
    }

    public function getBody(): FakeStream
    {
        return $this->body;
    }
}

final class FakeStream
{
    private string $content = '';

    public function write(string $data): int
    {
        $this->content .= $data;

        return strlen($data);
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
