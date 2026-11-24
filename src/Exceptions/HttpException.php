<?php

declare(strict_types=1);

namespace Kurama\Exceptions;

use RuntimeException;
use Throwable;

/** Base exception carrying an HTTP status for the error renderer. */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::messageFor($status), $status, $previous);
    }

    public static function messageFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'HTTP Error',
        };
    }
}
