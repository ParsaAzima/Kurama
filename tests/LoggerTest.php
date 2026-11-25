<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function testWritesLevelMessageAndContext(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-log-' . bin2hex(random_bytes(4));
        $path = $directory . '/nested/app.log';
        $logger = new Logger($path);

        try {
            $logger->info('User logged in', ['id' => 7]);
            $logger->error('Something failed');

            $contents = (string) file_get_contents($path);
            self::assertStringContainsString('INFO User logged in', $contents);
            self::assertStringContainsString('"id":7', $contents);
            self::assertStringContainsString('ERROR Something failed', $contents);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($directory . '/nested')) {
                rmdir($directory . '/nested');
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testLevelThresholdAndRotation(): void
    {
        $directory = sys_get_temp_dir() . '/kurama-log-' . bin2hex(random_bytes(4));
        $path = $directory . '/app.log';
        $logger = new Logger($path, 'warning', 10);

        try {
            $logger->info('ignored');
            self::assertFileDoesNotExist($path);

            $logger->error('first');
            $logger->error('second');

            self::assertFileExists($path . '.1');
            self::assertStringContainsString('second', (string) file_get_contents($path));
            self::assertStringContainsString('first', (string) file_get_contents($path . '.1'));
        } finally {
            foreach ([$path, $path . '.1'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testGenericLogMethod(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kurama_log_');
        $logger = new Logger($path);

        try {
            $logger->log('warning', 'Careful');
            self::assertStringContainsString('WARNING Careful', (string) file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
