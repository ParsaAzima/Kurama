<?php

declare(strict_types=1);

namespace Kurama\Server;

use Kurama\Request;

/** Incremental HTTP/1.1 request parser for the worker server. */
final class RequestParser
{
    /**
     * @param array<string, mixed> $server
     * @return array{request: Request, consumed: int}|null
     */
    public static function parse(string $buffer, array $server = []): ?array
    {
        $headerEnd = strpos($buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $head = substr($buffer, 0, $headerEnd);
        $lines = explode("\r\n", $head);
        $requestLine = array_shift($lines);
        if ($requestLine === null) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($requestLine));
        if ($parts === false || count($parts) < 2) {
            return null;
        }

        $method = strtoupper($parts[0]);
        $target = $parts[1];

        $headers = [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        $contentLength = 0;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Content-Length') === 0) {
                $contentLength = max(0, (int) $value);
            }
        }

        $bodyStart = $headerEnd + 4;
        if (strlen($buffer) < $bodyStart + $contentLength) {
            return null;
        }

        $body = substr($buffer, $bodyStart, $contentLength);
        $query = [];
        $uri = $target;
        if (($position = strpos($target, '?')) !== false) {
            $uri = substr($target, 0, $position);
            parse_str(substr($target, $position + 1), $query);
        }

        $contentType = '';
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Content-Type') === 0) {
                $contentType = strtolower($value);
            }
        }

        $parsedBody = [];
        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($body, true);
            $parsedBody = is_array($decoded) ? $decoded : [];
        } elseif (str_contains($contentType, 'x-www-form-urlencoded')) {
            parse_str($body, $parsedBody);
        }

        $cookies = [];
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Cookie') === 0) {
                foreach (explode(';', $value) as $cookie) {
                    $pair = explode('=', trim($cookie), 2);
                    if (count($pair) === 2) {
                        $cookies[$pair[0]] = $pair[1];
                    }
                }
            }
        }

        $request = new Request(
            method: $method,
            uri: $uri,
            query: $query,
            body: $parsedBody,
            headers: $headers,
            server: $server,
            cookies: $cookies,
        );

        return ['request' => $request, 'consumed' => $bodyStart + $contentLength];
    }
}
