<?php

declare(strict_types=1);

namespace Kurama\Examples;

use Kurama\Response;

/** Cacheable handler: class methods survive RouteCache::write(). */
final class CachedController
{
    public function show(string $id): Response
    {
        return Response::json(['id' => $id]);
    }

    public static function health(): Response
    {
        return Response::json(['ok' => true]);
    }
}
