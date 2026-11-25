<?php

declare(strict_types=1);

namespace Kurama\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class UploadedFile implements UploadedFileInterface
{
    private ?StreamInterface $stream;

    private bool $moved = false;

    public function __construct(
        StreamInterface|string $streamOrFile,
        private readonly ?int $size = null,
        private readonly int $error = UPLOAD_ERR_OK,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null,
    ) {
        $this->stream = $streamOrFile instanceof StreamInterface
            ? $streamOrFile
            : new Stream($streamOrFile);
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved.');
        }
        if ($this->stream === null) {
            throw new RuntimeException('No stream available for this upload.');
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved.');
        }
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error ' . $this->error . '.');
        }
        if (is_uploaded_file($this->stream instanceof Stream ? (string) $this->stream->getMetadata('uri') : '')) {
            // Native upload handled below via the stream path.
        }

        $stream = $this->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $handle = @fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open target [{$targetPath}].");
        }
        while (!$stream->eof()) {
            fwrite($handle, $stream->read(8192));
        }
        fclose($handle);

        $this->stream = null;
        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size ?? $this->stream?->getSize();
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
