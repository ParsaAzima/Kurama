<?php

declare(strict_types=1);

namespace Kurama\Exceptions;

final class CsrfTokenMismatchException extends HttpException
{
    public function __construct()
    {
        parent::__construct(419, 'CSRF token mismatch');
    }
}
