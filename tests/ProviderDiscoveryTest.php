<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\ProviderDiscovery;
use PHPUnit\Framework\TestCase;

final class ProviderDiscoveryTest extends TestCase
{
    public function testReadsExtraFromComposerFiles(): void
    {
        $base = sys_get_temp_dir() . '/kurama-discovery-' . bin2hex(random_bytes(4));
        mkdir($base . '/vendor/composer', 0775, true);

        file_put_contents($base . '/composer.json', json_encode([
            'extra' => ['kurama' => ['providers' => ['RootProvider']]],
        ]));
        file_put_contents($base . '/vendor/composer/installed.json', json_encode([
            'packages' => [
                ['extra' => ['kurama' => ['providers' => ['PackageProvider', 'RootProvider']]]],
                ['extra' => []],
            ],
        ]));

        try {
            self::assertSame(
                ['RootProvider', 'PackageProvider'],
                ProviderDiscovery::discover($base),
            );
        } finally {
            unlink($base . '/composer.json');
            unlink($base . '/vendor/composer/installed.json');
            rmdir($base . '/vendor/composer');
            rmdir($base . '/vendor');
            rmdir($base);
        }
    }

    public function testReturnsEmptyListWhenNothingIsConfigured(): void
    {
        self::assertSame([], ProviderDiscovery::fromArray([]));
        self::assertSame([], ProviderDiscovery::discover(sys_get_temp_dir() . '/does-not-exist-' . uniqid()));
    }
}
