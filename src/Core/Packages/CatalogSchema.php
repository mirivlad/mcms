<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use Composer\Semver\VersionParser;
use RuntimeException;

/** The index is generated from per-extension metadata, never from package PHP. */
final class CatalogSchema
{
    public const MAX_INDEX_BYTES = 1048576;

    public static function index(string $json): array
    {
        if (strlen($json) > self::MAX_INDEX_BYTES) {
            throw new RuntimeException('Catalog index is too large.');
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1 || ! is_array($data['extensions'] ?? null)
            || ! array_is_list($data['extensions']) || count($data['extensions']) > 200) {
            throw new RuntimeException('Unsupported catalog schema or invalid extension index.');
        }
        $seen = [];
        foreach ($data['extensions'] as $entry) {
            self::entry($entry);
            // IDs are globally unique, even between module/theme entries.
            if (isset($seen[$entry['id']])) {
                throw new RuntimeException('Duplicate catalog id: ' . $entry['id']);
            }
            $seen[$entry['id']] = true;
        }
        return $data;
    }

    public static function entry(array $entry): void
    {
        foreach (['id', 'type', 'name', 'description', 'author', 'license', 'version', 'project'] as $key) {
            if (! is_string($entry[$key] ?? null) || trim($entry[$key]) === '' || strlen($entry[$key]) > 4000) {
                throw new RuntimeException('Missing/invalid catalog metadata: ' . $key);
            }
        }
        PackageState::id($entry['id']);
        if (! in_array($entry['type'], ['module', 'theme'], true)
            || ! filter_var($entry['project'], FILTER_VALIDATE_URL) || ! str_starts_with($entry['project'], 'https://')
            || isset(parse_url($entry['project'])['user']) || isset(parse_url($entry['project'])['pass'])) {
            throw new RuntimeException('Invalid catalog type/project link.');
        }
        foreach (['tags', 'screenshots', 'versions'] as $key) {
            if (! is_array($entry[$key] ?? null) || ! array_is_list($entry[$key]) || count($entry[$key]) > 30) {
                throw new RuntimeException('Invalid catalog list: ' . $key);
            }
        }
        foreach ($entry['tags'] as $tag) {
            if (! is_string($tag) || ! preg_match('/^[a-z0-9-]{1,40}$/D', $tag)) {
                throw new RuntimeException('Invalid catalog tag.');
            }
        }
        foreach ($entry['screenshots'] as $url) {
            if (! is_string($url)) {
                throw new RuntimeException('Invalid screenshot URL.');
            }
            GitHubCatalogSource::assertUrl($url);
            $prefix = GitHubCatalogSource::BASE . $entry['type'] . 's/' . $entry['id'] . '/screenshots/';
            if (! str_starts_with($url, $prefix) || ! preg_match('/\.(png|jpg|jpeg|webp|svg)$/D', $url)) {
                throw new RuntimeException('Screenshots must belong to the extension catalog directory.');
            }
        }
        $versions = [];
        foreach ($entry['versions'] as $version) {
            if (! is_array($version)) {
                throw new RuntimeException('Invalid version entry.');
            }
            self::version($version, $entry['type']);
            if (isset($versions[$version['version']])) {
                throw new RuntimeException('Duplicate catalog version.');
            }
            $versions[$version['version']] = true;
        }
        if (! isset($versions[$entry['version']])) {
            throw new RuntimeException('Current version is absent from published versions.');
        }
        foreach (array_keys($versions) as $version) {
            if (\Composer\Semver\Comparator::greaterThan($version, $entry['version'])) {
                throw new RuntimeException('Current version must be the latest published version.');
            }
        }
    }

    private static function version(array $version, string $type): void
    {
        foreach (['version', 'mcms', 'download', 'sha256', 'changelog'] as $key) {
            if (! is_string($version[$key] ?? null) || $version[$key] === '' || strlen($version[$key]) > 8000) {
                throw new RuntimeException('Invalid catalog release: ' . $key);
            }
        }
        if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $version['version'])
            || ! preg_match('/^[a-f0-9]{64}$/D', $version['sha256']) || ! is_int($version['bytes'] ?? null)
            || $version['bytes'] <= 0 || $version['bytes'] > ZipPackage::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Invalid catalog version, size or SHA-256.');
        }
        (new VersionParser())->normalize($version['version']);
        (new VersionParser())->parseConstraints($version['mcms']);
        GitHubCatalogSource::assertUrl($version['download'], true);
        if (! str_ends_with($version['download'], '.zip') || ! is_array($version['requires'] ?? null)
            || ! array_is_list($version['requires']) || ($type === 'theme' && $version['requires'] !== [])) {
            throw new RuntimeException('Invalid archive URL or dependencies.');
        }
        foreach ($version['requires'] as $dependency) {
            $id = is_array($dependency) ? ($dependency['id'] ?? '') : $dependency;
            $constraint = is_array($dependency) ? ($dependency['version'] ?? '*') : '*';
            if (! is_string($id) || ! is_string($constraint) || trim($constraint) === '') {
                throw new RuntimeException('Invalid catalog dependency.');
            }
            PackageState::id($id);
            (new VersionParser())->parseConstraints($constraint);
        }
    }

    public static function assertPackage(array $entry, array $release, object $manifest): void
    {
        if ($manifest->id !== $entry['id'] || $manifest->version !== $release['version'] || $manifest->mcms !== $release['mcms']
            || ($entry['type'] === 'module' ? $manifest->requires : []) !== $release['requires']) {
            throw new RuntimeException('Catalog identity, version, requirements or dependencies differ from the ZIP manifest.');
        }
    }
}
