<?php

declare(strict_types=1);

namespace MCMS\Core\Themes;

use InvalidArgumentException;
use JsonException;

final class ThemeManifest
{
    /**
     * @param array<string, string> $templates
     * @param array<string, array{label:string,description:?string,max_depth:?int}> $menuLocations
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $namespace,
        public readonly string $description,
        public readonly ?string $author,
        public readonly ?string $homepage,
        public readonly ?string $screenshot,
        public readonly array $templates,
        public readonly array $menuLocations,
        public readonly string $path,
    ) {
    }

    public static function fromFile(string $manifestPath): self
    {
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("Theme manifest does not exist: {$manifestPath}");
        }

        try {
            $data = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                "Invalid theme manifest JSON: {$manifestPath}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException("Theme manifest must contain an object: {$manifestPath}");
        }

        $id = self::requiredString($data, 'id', $manifestPath);
        if (preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
            throw new InvalidArgumentException(
                "Theme id must use lowercase ASCII letters, numbers and hyphens: {$id}"
            );
        }

        if (basename(dirname($manifestPath)) !== $id) {
            throw new InvalidArgumentException(
                "Theme directory name must match theme id {$id}."
            );
        }

        $name = self::requiredString($data, 'name', $manifestPath);
        $version = self::requiredString($data, 'version', $manifestPath);
        if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new InvalidArgumentException("Invalid theme version: {$version}");
        }

        $namespace = trim(self::requiredString($data, 'namespace', $manifestPath), '\\');
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) !== 1) {
            throw new InvalidArgumentException("Invalid theme namespace: {$namespace}");
        }

        $description = trim((string) ($data['description'] ?? ''));
        $author = self::optionalString($data['author'] ?? null);
        $homepage = self::optionalString($data['homepage'] ?? null);
        if ($homepage !== null && filter_var($homepage, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Invalid theme homepage URL: {$homepage}");
        }

        $screenshot = self::optionalRelativePath($data['screenshot'] ?? null, 'screenshot');
        $templates = self::templates($data['templates'] ?? []);
        $menuLocations = self::menuLocations($data['menu_locations'] ?? []);

        return new self(
            $id,
            $name,
            $version,
            $namespace,
            $description,
            $author,
            $homepage,
            $screenshot,
            $templates,
            $menuLocations,
            dirname($manifestPath),
        );
    }

    public function view(string $logicalTemplate): ?string
    {
        $relative = $this->templates[$logicalTemplate] ?? null;
        if ($relative === null) {
            return null;
        }

        return $this->namespace . '\\Views\\' . str_replace('/', '\\', $relative);
    }

    public function assetBaseUrl(): string
    {
        return 'assets/themes/' . rawurlencode($this->id) . '/';
    }

    private static function requiredString(array $data, string $key, string $manifestPath): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Theme manifest {$manifestPath} requires non-empty string field {$key}."
            );
        }

        return trim($value);
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Optional theme manifest values must be strings.');
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function optionalRelativePath(mixed $value, string $field): ?string
    {
        $value = self::optionalString($value);
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, '/')
            || str_contains($value, '\\')
            || str_contains($value, '..')
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid relative theme path in {$field}: {$value}");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function templates(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('Theme templates must be an object.');
        }

        $templates = [];

        foreach ($raw as $logical => $view) {
            if (! is_string($logical)
                || preg_match('/^[a-z][a-z0-9._-]*$/', $logical) !== 1
                || ! is_string($view)
                || trim($view) === '') {
                throw new InvalidArgumentException('Invalid theme template mapping.');
            }

            $relative = self::optionalRelativePath(trim($view), 'templates.' . $logical);
            if ($relative === null) {
                throw new InvalidArgumentException("Theme template {$logical} cannot be empty.");
            }

            $templates[$logical] = $relative;
        }

        ksort($templates);

        return $templates;
    }

    /**
     * @return array<string, array{label:string,description:?string,max_depth:?int}>
     */
    private static function menuLocations(mixed $raw): array
    {
        if (! is_array($raw)) {
            throw new InvalidArgumentException('Theme menu_locations must be an object.');
        }

        $locations = [];

        foreach ($raw as $id => $definition) {
            if (! is_string($id)
                || preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $id) !== 1
                || ! is_array($definition)) {
                throw new InvalidArgumentException('Invalid theme menu location definition.');
            }

            $label = $definition['label'] ?? null;
            if (! is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException("Theme menu location {$id} requires a label.");
            }

            $description = self::optionalString($definition['description'] ?? null);
            $maxDepth = $definition['max_depth'] ?? null;

            if ($maxDepth !== null) {
                if (! is_int($maxDepth) || $maxDepth < 1 || $maxDepth > 20) {
                    throw new InvalidArgumentException(
                        "Theme menu location {$id} max_depth must be between 1 and 20."
                    );
                }
            }

            $locations[$id] = [
                'label' => trim($label),
                'description' => $description,
                'max_depth' => $maxDepth,
            ];
        }

        ksort($locations);

        return $locations;
    }
}