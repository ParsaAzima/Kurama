<?php

declare(strict_types=1);

namespace Kurama;

/**
 * Reads provider class names from composer.json `extra.kurama.providers`
 * in the root project and installed packages.
 */
final class ProviderDiscovery
{
    /** @return list<class-string> */
    public static function discover(string $basePath): array
    {
        $providers = self::fromComposer($basePath . '/composer.json');

        $installed = $basePath . '/vendor/composer/installed.json';
        if (is_file($installed)) {
            $contents = file_get_contents($installed);
            $data = $contents === false ? null : json_decode($contents, true);
            $packages = is_array($data) ? ($data['packages'] ?? $data) : [];

            foreach ($packages as $package) {
                if (is_array($package)) {
                    $providers = array_merge($providers, self::fromArray($package['extra'] ?? []));
                }
            }
        }

        return array_values(array_unique(array_filter($providers, 'is_string')));
    }

    /** @return list<string> */
    public static function fromComposer(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $data = $contents === false ? null : json_decode($contents, true);

        return self::fromArray(is_array($data) ? ($data['extra'] ?? []) : []);
    }

    /** @return list<string> */
    public static function fromArray(array $extra): array
    {
        $providers = $extra['kurama']['providers'] ?? [];

        return is_array($providers) ? array_values(array_filter($providers, 'is_string')) : [];
    }
}
