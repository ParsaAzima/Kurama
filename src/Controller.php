<?php

declare(strict_types=1);

namespace Kurama;

use LogicException;

/**
 * Optional base class for action controllers.
 *
 * Kurama resolves controller handlers and injects the current request and a
 * fresh response before calling the action. No container is involved.
 */
abstract class Controller
{
    private ?Request $request = null;

    private ?Response $response = null;

    public function setContext(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    protected function request(): Request
    {
        return $this->request ?? throw new LogicException('Controller has no request context.');
    }

    protected function response(): Response
    {
        return $this->response ?? throw new LogicException('Controller has no response context.');
    }

    protected function param(string $name, mixed $default = null): mixed
    {
        return $this->request()->param($name, $default);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }

    protected function text(string $content, int $status = 200, array $headers = []): Response
    {
        return Response::text($content, $status, $headers);
    }

    protected function html(string $content, int $status = 200, array $headers = []): Response
    {
        return Response::html($content, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}
