<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    /** @param resource|string $body */
    public function __construct(mixed $body = 'php://temp', private readonly string $mode = 'r+')
    {
        if (is_string($body)) {
            $resource = fopen('php://temp', 'r+');
            if ($resource === false) {
                throw new RuntimeException('Unable to create a stream.');
            }
            fwrite($resource, $body);
            rewind($resource);
            $this->resource = $resource;

            return;
        }

        if (!is_resource($body)) {
            throw new RuntimeException('Stream body must be a resource or string.');
        }
        $this->resource = $body;
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }
        $this->resource = null;
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }
        $stats = fstat($this->resource);

        return $stats === false ? null : (int) $stats['size'];
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }
        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to read stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null
            && (bool) stream_get_meta_data($this->resource)['seekable'];
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }
        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek in stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if ($this->resource === null) {
            return false;
        }
        $mode = stream_get_meta_data($this->resource)['mode'];

        return str_contains($mode, 'w') || str_contains($mode, 'a') || str_contains($mode, '+');
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }
        $written = fwrite($this->resource, $string);
        if ($written === false) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        if ($this->resource === null) {
            return false;
        }
        $mode = stream_get_meta_data($this->resource)['mode'];

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new RuntimeException('Length must be non-negative.');
        }
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $data;
    }

    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? null : null;
        }
        $metadata = stream_get_meta_data($this->resource);

        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }
}
