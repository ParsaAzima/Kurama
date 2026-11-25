<?php

declare(strict_types=1);

namespace Kurama\Bridge;

use Kurama\Request;
use Kurama\Response;

/**
 * Duck-typed converters for PSR-7 style objects.
 *
 * Kurama does not depend on psr/http-message; these methods work with any
 * object exposing the PSR-7 getters, so the dependency stays optional.
 */
final class PsrBridge
{
    public static function isPsrRequest(object $request): bool
    {
        foreach (['getMethod', 'getUri', 'getHeaders', 'getQueryParams', 'getParsedBody'] as $method) {
            if (!method_exists($request, $method)) {
                return false;
            }
        }

        return true;
    }

    public static function toKuramaRequest(object $request): Request
    {
        if (!self::isPsrRequest($request)) {
            throw new \InvalidArgumentException('Object does not look like a PSR-7 request.');
        }

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', array_map('strval', (array) $values));
        }

        $body = $request->getParsedBody();

        return new Request(
            method: strtoupper((string) $request->getMethod()),
            uri: (string) $request->getUri(),
            query: (array) $request->getQueryParams(),
            body: is_object($body) ? (array) get_object_vars($body) : (array) $body,
            headers: $headers,
            server: method_exists($request, 'getServerParams') ? (array) $request->getServerParams() : [],
            cookies: method_exists($request, 'getCookieParams') ? (array) $request->getCookieParams() : [],
            files: method_exists($request, 'getUploadedFiles') ? (array) $request->getUploadedFiles() : [],
        );
    }

    public static function toKuramaResponse(object $psrResponse): Response
    {
        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', array_map('strval', (array) $values));
        }

        return new Response(
            (string) $psrResponse->getBody(),
            (int) $psrResponse->getStatusCode(),
            $headers,
        );
    }

    /** Convert using any PSR-17 ResponseFactoryInterface implementation. */
    public static function toPsrResponse(Response $response, object $responseFactory): object
    {
        if (!method_exists($responseFactory, 'createResponse')) {
            throw new \InvalidArgumentException('Object does not look like a PSR-17 response factory.');
        }

        $psr = $responseFactory->createResponse($response->getStatus());
        foreach ($response->getHeaders() as $name => $value) {
            $psr = $psr->withHeader($name, $value);
        }

        $psr->getBody()->write($response->getContent());

        return $psr;
    }
}
