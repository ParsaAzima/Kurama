<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testLoadsAndParsesValues(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $keys = [
            'plain' => 'KURAMA_ENV_PLAIN_' . $suffix,
            'double' => 'KURAMA_ENV_DOUBLE_' . $suffix,
            'single' => 'KURAMA_ENV_SINGLE_' . $suffix,
            'inline' => 'KURAMA_ENV_INLINE_' . $suffix,
        ];

        $file = tempnam(sys_get_temp_dir(), 'kurama_env_');
        file_put_contents($file, implode("\n", [
            '# a comment',
            $keys['plain'] . '=value',
            $keys['double'] . '="hello world"',
            'export ' . $keys['single'] . "='literal'",
            $keys['inline'] . '=inline # trailing comment',
        ]));

        try {
            $env = new Environment();
            $env->load($file);

            self::assertSame('value', $env->get($keys['plain']));
            self::assertSame('hello world', $env->get($keys['double']));
            self::assertSame('literal', $env->get($keys['single']));
            self::assertSame('inline', $env->get($keys['inline']));
            self::assertTrue($env->has($keys['plain']));
            self::assertSame('fallback', $env->get('KURAMA_MISSING_KEY', 'fallback'));
            self::assertArrayHasKey($keys['plain'], $env->all());
            self::assertSame('value', $_ENV[$keys['plain']]);
            self::assertSame('value', getenv($keys['plain']));
        } finally {
            foreach ($keys as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            }
            unlink($file);
        }
    }

    public function testDoesNotOverwriteExistingVariablesByDefault(): void
    {
        $key = 'KURAMA_ENV_OVERWRITE_' . bin2hex(random_bytes(4));
        $file = tempnam(sys_get_temp_dir(), 'kurama_env_');
        file_put_contents($file, $key . '=new');

        try {
            $_ENV[$key] = 'original';
            putenv($key . '=original');

            $env = new Environment();
            $env->load($file);
            self::assertSame('original', $env->get($key));

            $env->load($file, true);
            self::assertSame('new', $env->get($key));
        } finally {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
            unlink($file);
        }
    }
}
