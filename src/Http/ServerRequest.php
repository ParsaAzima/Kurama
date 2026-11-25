<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest extends Request implements ServerRequestInterface
{
    /** @param array<string, mixed> $serverParams */
    public function __construct(
        string $method = 'GET',
        UriInterface|string $uri = '',
        mixed $body = '',
        array $headers = [],
        string $protocolVersion = '1.1',
        private array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private mixed $parsedBody = null,
        /** @var array<string, UploadedFileInterface> */
        private array $uploadedFiles = [],
        private array $attributes = [],
    ) {
        parent::__construct($method, $uri, $body, $headers, $protocolVersion);
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;

        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }

    public function getParsedBody(): mixed
    {
        return $this->parsedBody;
    }

    public function withParsedBody(mixed $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }
}
