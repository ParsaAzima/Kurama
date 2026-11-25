<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response implements ResponseInterface
{
    use MessageTrait;

    private Stream $body;

    public function __construct(
        private int $status = 200,
        string $reasonPhrase = '',
        mixed $body = '',
        array $headers = [],
        string $protocolVersion = '1.1',
    ) {
        $this->body = $body instanceof Stream ? $body : new Stream($body);
        $this->protocolVersion = $protocolVersion;
        if ($reasonPhrase !== '') {
            $this->reasonPhrase = $reasonPhrase;
        }
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = is_array($value)
                ? array_values(array_map('strval', $value))
                : [(string) $value];
        }
    }

    private string $reasonPhrase = '';

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException('Invalid status code.');
        }
        $clone = clone $this;
        $clone->status = $code;
        $clone->reasonPhrase = $reasonPhrase !== ''
            ? $reasonPhrase
            : self::defaultReason($code);

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase !== '' ? $this->reasonPhrase : self::defaultReason($this->status);
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body instanceof Stream ? $body : new Stream((string) $body);

        return $clone;
    }

    private static function defaultReason(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => '',
        };
    }
}
