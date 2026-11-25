<?php

declare(strict_types=1);

namespace Kurama\Bridge;

use Kurama\Http\Response as PsrResponse;
use Kurama\Http\ServerRequest as PsrServerRequest;
use Kurama\Request;
use Kurama\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Conversions between Kurama's lightweight objects and full PSR-7 messages. */
final class PsrBridge
{
    public static function isPsrRequest(object $request): bool
    {
        return $request instanceof ServerRequestInterface;
    }

    public static function toKuramaRequest(object $request): Request
    {
        if (!$request instanceof ServerRequestInterface) {
            throw new \InvalidArgumentException('Expected a PSR-7 ServerRequestInterface.');
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', array_map('strval', $values));
        }

        $body = $request->getParsedBody();

        return new Request(
            method: $request->getMethod(),
            uri: $request->getUri()->getPath() !== ''
                ? $request->getUri()->getPath()
                : '/',
            query: $request->getQueryParams(),
            body: is_object($body) ? (array) get_object_vars($body) : (array) $body,
            headers: $headers,
            server: $request->getServerParams(),
            cookies: $request->getCookieParams(),
            files: $request->getUploadedFiles(),
        );
    }

    public static function toPsrRequest(Request $request): ServerRequestInterface
    {
        return (new PsrServerRequest(
            $request->method(),
            $request->uri,
            (string) $request->getBody(),
        ))
            ->withQueryParams($request->getQueryParams())
            ->withParsedBody($request->getParsedBody())
            ->withCookieParams($request->cookies)
            ->withUploadedFiles($request->files);
    }

    public static function toKuramaResponse(object $psrResponse): Response
    {
        if (!$psrResponse instanceof ResponseInterface) {
            throw new \InvalidArgumentException('Expected a PSR-7 ResponseInterface.');
        }

        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', array_map('strval', $values));
        }

        return new Response(
            (string) $psrResponse->getBody(),
            $psrResponse->getStatusCode(),
            $headers,
        );
    }

    public static function toPsrResponse(
        Response $response,
        ?object $responseFactory = null,
    ): ResponseInterface {
        if ($responseFactory instanceof ResponseFactoryInterface) {
            $psr = $responseFactory->createResponse($response->getStatus());
        } elseif ($responseFactory !== null && method_exists($responseFactory, 'createResponse')) {
            $psr = $responseFactory->createResponse($response->getStatus());
        } else {
            $psr = new PsrResponse($response->getStatus());
        }

        foreach ($response->getHeaders() as $name => $value) {
            $psr = $psr->withHeader($name, explode(', ', $value));
        }
        foreach ($response->getCookies() as $cookie) {
            $psr = $psr->withAddedHeader('Set-Cookie', $cookie->toHeader());
        }

        $psr->getBody()->write($response->getContent());

        return $psr;
    }

    /** Wrap a Kurama middleware as PSR-15 when those interfaces are installed. */
    public static function psr15Middleware(callable $middleware, ?object $responseFactory = null): object
    {
        if (
            !interface_exists(\Psr\Http\Server\MiddlewareInterface::class)
            || !interface_exists(\Psr\Http\Server\RequestHandlerInterface::class)
        ) {
            throw new \InvalidArgumentException('PSR-15 interfaces are not installed.');
        }

        return new class($middleware, $responseFactory) implements \Psr\Http\Server\MiddlewareInterface {
            public function __construct(
                private readonly mixed $middleware,
                private readonly ?object $responseFactory,
            ) {
            }

            public function process(
                \Psr\Http\Message\ServerRequestInterface $request,
                \Psr\Http\Server\RequestHandlerInterface $handler,
            ): \Psr\Http\Message\ResponseInterface {
                $kuramaRequest = PsrBridge::toKuramaRequest($request);
                $next = static fn (): Response => PsrBridge::toKuramaResponse($handler->handle($request));

                $response = ($this->middleware)($kuramaRequest, $next);

                return PsrBridge::toPsrResponse($response, $this->responseFactory);
            }
        };
    }
}
