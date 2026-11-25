<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface
{
    use MessageTrait;

    private string $method;

    private UriInterface $uri;

    private Stream $body;

    private ?string $requestTarget = null;

    public function __construct(
        string $method = 'GET',
        UriInterface|string $uri = '',
        mixed $body = '',
        array $headers = [],
        string $protocolVersion = '1.1',
    ) {
        $this->method = strtoupper($method);
        $this->uri = is_string($uri) ? new Uri($uri) : $uri;
        $this->body = $body instanceof Stream ? $body : new Stream($body);
        $this->protocolVersion = $protocolVersion;

        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = is_array($value)
                ? array_values(array_map('strval', $value))
                : [(string) $value];
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        if ($method === '') {
            throw new \InvalidArgumentException('Method must not be empty.');
        }
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost && $uri->getHost() !== '' && !$clone->hasHeader('Host')) {
            $clone = $clone->withHeader('Host', $uri->getHost());
        }

        return $clone;
    }

    public function getBody(): \Psr\Http\Message\StreamInterface
    {
        return $this->body;
    }

    public function withBody(\Psr\Http\Message\StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body instanceof Stream ? $body : new Stream((string) $body);

        return $clone;
    }
}
