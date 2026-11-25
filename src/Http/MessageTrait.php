<?php

declare(strict_types=1);

namespace Kurama\Http;

use InvalidArgumentException;

/** Shared PSR-7 MessageInterface implementation. */
trait MessageTrait
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    private string $protocolVersion = '1.1';

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /** @return array<string, list<string>> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$this->headerKey($name)]);
    }

    /** @return list<string> */
    public function getHeader(string $name): array
    {
        return $this->headers[$this->headerKey($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->headers[$this->headerKey($name)] = $this->normalizeHeaderValue($value);

        return $clone;
    }

    public function withAddedHeader(string $name, mixed $value): static
    {
        $clone = clone $this;
        $key = $this->headerKey($name);
        $clone->headers[$key] = array_merge(
            $clone->headers[$key] ?? [],
            $this->normalizeHeaderValue($value),
        );

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        unset($clone->headers[$this->headerKey($name)]);

        return $clone;
    }

    private function headerKey(string $name): string
    {
        return strtolower($name);
    }

    /** @return list<string> */
    private function normalizeHeaderValue(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Header value must be a string or array of strings.');
        }

        return array_values(array_map('strval', $value));
    }
}
