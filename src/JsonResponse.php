<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Response that encodes structured data as JSON.
 */
final class JsonResponse extends Response
{
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        parent::__construct(
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            $status,
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers),
        );
    }
}
