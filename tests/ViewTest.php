<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Application;
use Kurama\View\CallableRenderer;
use Kurama\View\Renderer;
use Kurama\View\ViewCache;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function testCallableRendererAcceptsCallableAndObject(): void
    {
        $fromClosure = new CallableRenderer(fn (string $t, array $d): string => $t . ':' . ($d['x'] ?? ''));
        $fromObject = new CallableRenderer(new RenderObject());

        self::assertSame('a:1', $fromClosure->render('a', ['x' => 1]));
        self::assertSame('object:a', $fromObject->render('a'));
    }

    public function testRendererSelectsEngineByExtension(): void
    {
        $renderer = new Renderer(new CallableRenderer(fn (string $t): string => 'native:' . $t));
        $renderer->engine('twig', new CallableRenderer(fn (string $t): string => 'twig:' . $t));

        self::assertSame('native:home.php', $renderer->render('home.php'));
        self::assertSame('twig:home.twig', $renderer->render('home.twig'));
    }

    public function testViewCacheGetPutForgetAndFlush(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-view-' . bin2hex(random_bytes(4));
        $cache = new ViewCache($directory);

        try {
            $cache->put('a', 'one');
            $cache->put('b', 'two');
            self::assertSame('one', $cache->get('a'));

            $cache->forget('a');
            self::assertNull($cache->get('a'));

            $cache->flush();
            self::assertNull($cache->get('b'));
        } finally {
            foreach (glob($directory . '/*.view') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testApplicationUsesCustomEngineAndCachesOutput(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-view-' . bin2hex(random_bytes(4));
        $app = new Application([
            'debug' => true,
            'discovery.enabled' => false,
            'view.engine' => fn (string $t, array $d): string => 'custom:' . $t . ':' . ($d['name'] ?? ''),
            'view.cache.enabled' => true,
            'view.cache.path' => $directory,
        ]);

        try {
            $first = $app->render('page.twig', ['name' => 'Kurama'])->getContent();
            $second = $app->render('page.twig', ['name' => 'Kurama'])->getContent();

            self::assertSame('custom:page.twig:Kurama', $first);
            self::assertSame($first, $second);
            self::assertCount(1, glob($directory . '/*.view') ?: []);
        } finally {
            foreach (glob($directory . '/*.view') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}

final class RenderObject
{
    public function render(string $template): string
    {
        return 'object:' . $template;
    }
}
