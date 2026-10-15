<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use MCMS\Core\Modules\ModulePackages;
use MCMS\Core\Themes\ThemePackages;
use RuntimeException;

final class CatalogValidator
{
    public static function validate(string $repository, bool $build = false): int
    {
        $root = $repository . '/catalog';
        $entries = [];
        foreach (glob($root . '/*/*/catalog.json') ?: [] as $file) {
            $entry = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            CatalogSchema::entry($entry);
            if (dirname($file) !== $root . '/' . $entry['type'] . 's/' . $entry['id']) {
                throw new RuntimeException('Catalog path differs from package type/id: ' . $file);
            }
            foreach ($entry['screenshots'] as $url) {
                $path = $root . '/' . substr($url, strlen(GitHubCatalogSource::BASE));
                if (! is_file($path) || is_link($path) || filesize($path) === 0 || filesize($path) > 8 * 1024 * 1024) {
                    throw new RuntimeException('Missing/invalid screenshot: ' . $path);
                }
            }
            foreach ($entry['versions'] as $release) {
                $temporary = false;
                if (str_starts_with($release['download'], GitHubCatalogSource::BASE)) {
                    $archive = $root . '/' . substr($release['download'], strlen(GitHubCatalogSource::BASE));
                } else {
                    $archive = tempnam(sys_get_temp_dir(), 'mcms-catalog-');
                    $temporary = true;

                }
                try {
                    if ($temporary) {
                        if ($archive === false) {
                            throw new RuntimeException('Cannot stage a release archive.');
                        }
                        file_put_contents($archive, (new GitHubCatalogSource())->fetch($release['download'], $release['bytes']));
                    }
                    if (! is_file($archive) || is_link($archive) || filesize($archive) !== $release['bytes']
                        || ! hash_equals($release['sha256'], (string) hash_file('sha256', $archive))) {
                        throw new RuntimeException('Catalog archive size/SHA-256 mismatch: ' . $release['download']);
                    }
                    $preview = $entry['type'] === 'module' ? ModulePackages::inspectArchive($archive) : ThemePackages::inspectArchive($archive);
                    CatalogSchema::assertPackage($entry, $release, $preview['manifest']);
                } finally {
                    if ($temporary && is_string($archive)) {
                        PackageState::remove($archive);
                    }
                }
            }
            $entries[] = $entry;
        }
        usort($entries, fn ($a, $b) => strcmp($a['id'], $b['id']));
        $json = json_encode(['schema' => 1, 'extensions' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        CatalogSchema::index($json); // duplicates/schema are checked before writing anything
        if ($build) {
            if (file_put_contents($root . '/index.json', $json) !== strlen($json)) {
                throw new RuntimeException('Cannot write catalog index.');
            }
        } elseif (CatalogSchema::index((string) file_get_contents($root . '/index.json')) !== json_decode($json, true)) {
            throw new RuntimeException('Catalog index differs from per-package metadata. Run mcms:catalog:validate --build.');
        }
        return count($entries);
    }
}
