<?php

declare(strict_types=1);

namespace Kurama\Tests;

use InvalidArgumentException;
use Kurama\Request;
use Kurama\Response;
use Kurama\RouteCache;
use Kurama\Router;
use PHPUnit\Framework\TestCase;

final class RouteCacheTest extends TestCase
{
    public function testDumpAndLoadPreservesRoutesNamesAndMiddleware(): void
    {
        $path = sys_get_temp_dir() . '/kurama-cache-' . bin2hex(random_bytes(4)) . '.php';
        $router = new Router();
        $router->get('/users/{id:\d+}', [CachedTestController::class, 'show'])->name('user.show');
        $router->get('/ping', [CachedTestController::class, 'ping'])
            ->middleware([new CachedTestMiddleware(), 'handle']);

        try {
            RouteCache::write($router, $path);
            $loaded = RouteCache::load($path);

            self::assertSame(
                ['id' => '42'],
                $loaded->dispatch('GET', '/users/42'),
            );
            self::assertNotNull($loaded->named('user.show'));
            self::assertSame('/users/42', $loaded->url('user.show', ['id' => 42]));

            $match = $loaded->resolve('GET', '/ping');
            self::assertNotNull($match);
            self::assertCount(1, $match['route']->middlewares());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testClosuresCannotBeCached(): void
    {
        $path = sys_get_temp_dir() . '/kurama-cache-' . bin2hex(random_bytes(4)) . '.php';
        $router = new Router();
        $router->get('/x', static fn (): string => 'x');

        try {
            $this->expectException(InvalidArgumentException::class);
            RouteCache::write($router, $path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testStaleCacheIsDetected(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-fresh-' . bin2hex(random_bytes(4));
        mkdir($directory, 0775, true);
        $source = $directory . '/routes.php';
        $cache = $directory . '/cache.php';
        file_put_contents($source, '<?php return [];');

        try {
            RouteCache::write(new Router(), $cache, null, [$source]);
            self::assertTrue(RouteCache::isFresh($cache, [$source]));

            file_put_contents($source, '<?php // changed');
            touch($source, time() + 10);

            self::assertFalse(RouteCache::isFresh($cache, [$source]));
        } finally {
            foreach ([$source, $cache] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }

    public function testMissingCacheFileThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RouteCache::load(sys_get_temp_dir() . '/missing-' . uniqid() . '.php');
    }
}

final class CachedTestController
{
    public function show(string $id): array
    {
        return ['id' => $id];
    }

    public static function ping(): array
    {
        return ['ok' => true];
    }
}

final class CachedTestMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Cached', 'yes');
    }
}
