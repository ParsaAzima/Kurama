<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequestFactory implements ServerRequestFactoryInterface
{
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest(
            $method,
            $uri instanceof UriInterface ? $uri : (string) $uri,
            '',
            [],
            '1.1',
            $serverParams,
        );
    }
}
