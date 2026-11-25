<?php

declare(strict_types=1);

namespace Kurama\Server;

use Kurama\Response;

final class ResponseWriter
{
    public static function write(Response $response, bool $keepAlive = true): string
    {
        $headers = $response->getHeaders();
        $headers['Content-Length'] ??= (string) strlen($response->getContent());
        $headers['Connection'] = $keepAlive ? 'keep-alive' : 'close';

        $head = "HTTP/1.1 " . $response->getStatus() . " " . self::statusText($response->getStatus()) . "\r\n";
        foreach ($headers as $name => $value) {
            $head .= $name . ': ' . $value . "\r\n";
        }
        foreach ($response->getCookies() as $cookie) {
            $head .= 'Set-Cookie: ' . $cookie->toHeader() . "\r\n";
        }

        return $head . "\r\n" . $response->getContent();
    }

    private static function statusText(int $status): string
    {
        return match ($status) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Status',
        };
    }
}
