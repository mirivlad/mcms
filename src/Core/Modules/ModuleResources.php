<?php

declare(strict_types=1);

namespace MCMS\Core\Modules;

use CodeIgniter\Database\BaseConnection;
use MCMS\Core\Packages\PackageState;
use RuntimeException;

/** Ownership is observed when migrations create tables, never inferred from names. */
final class ModuleResources
{
    private static function tables(BaseConnection $db): array
    {
        unset($db->dataCache['table_names']);
        return $db->listTables();
    }

    public static function contract(ModuleManifest $manifest): ?array
    {
        $data = $manifest->cleanup;
        if ($data === []) {
            return null;
        }
        if (($data['schema'] ?? null) !== 1 || ($data['complete'] ?? null) !== true
            || ! is_array($data['tables'] ?? null) || ! array_is_list($data['tables'])) {
            throw new RuntimeException('Cleanup requires schema=1, complete=true and an ordered tables list.');
        }
        foreach ($data['tables'] as $table) {
            if (! is_string($table) || preg_match('/^[a-z][a-z0-9_]*$/D', $table) !== 1) {
                throw new RuntimeException('Invalid owned table name.');
            }
        }
        if (count(array_unique($data['tables'])) !== count($data['tables'])) {
            throw new RuntimeException('Duplicate owned table.');
        }
        return $data;
    }

    public static function migrate(BaseConnection $db, ModuleManifest $manifest, ?string $packagesPath, callable $migrate): mixed
    {
        if ($packagesPath === null || realpath($manifest->path) !== realpath($packagesPath . '/' . basename($manifest->path))) {
            return $migrate();
        }
        $path = dirname($packagesPath) . '/ownership/' . $manifest->id . '.json';
        $owned = PackageState::read($path) ?? ['tables' => []];
        $before = self::tables($db);
        $shared = array_diff($before, $owned['tables'], [$db->prefixTable(config('Migrations')->table)]);
        $schemas = [];
        foreach ($shared as $table) {
            $schemas[$table] = self::schema($db, $table);
        }
        $wasIncomplete = $owned['incomplete'] ?? false;
        PackageState::write($path, [...$owned, 'incomplete' => true]);
        $result = $migrate();
        if ($result !== true) {
            throw new RuntimeException('Module migrations did not complete.');
        }
        $runner = \Config\Services::migrations()->setNamespace($manifest->namespace);
        $history = array_map(fn ($row) => $runner->getObjectUid($row), $runner->getHistory(''));
        foreach ($runner->findMigrations() as $migration) {
            if (! in_array($runner->getObjectUid($migration), $history, true)) {
                throw new RuntimeException('Module migration was not recorded.');
            }
        }
        $after = self::tables($db);
        foreach ($schemas as $table => $schema) {
            if (! in_array($table, $after, true) || self::schema($db, $table) !== $schema) {
                $owned['uncontrolled_tables'][] = $table;
            }
        }
        $owned['tables'] = array_values(array_unique([...$owned['tables'], ...array_diff($after, $before)]));
        $owned['incomplete'] = $wasIncomplete;
        PackageState::write($path, $owned);
        return $result;
    }

    private static function schema(BaseConnection $db, string $table): string
    {
        return hash('sha256', json_encode([$db->getFieldData($table), $db->getIndexData($table), $db->getForeignKeyData($table)], JSON_THROW_ON_ERROR));
    }

    public static function assertCleanup(BaseConnection $db, ModuleManifest $manifest, string $root): array
    {
        $contract = self::contract($manifest);
        if ($contract === null) {
            throw new RuntimeException('This module has no complete cleanup contract. Keep its data instead.');
        }
        $receipt = PackageState::read($root . '/ownership/' . $manifest->id . '.json');
        if (($receipt['incomplete'] ?? false) === true) {
            throw new RuntimeException('An interrupted migration left ownership unproven. Keep the data or restore a verified backup.');
        }
        if (($receipt['uncontrolled_tables'] ?? []) !== []) {
            throw new RuntimeException('Module migrations changed shared tables. Complete cleanup is unavailable.');
        }
        $owned = $receipt['tables'] ?? [];
        $tables = array_map(fn ($name) => $db->prefixTable($name), $contract['tables']);
        $existing = self::tables($db);
        // Unknown/pre-existing/shared tables can never be admitted by a later manifest.
        if (array_diff(array_intersect($tables, $existing), $owned) !== [] || array_diff($owned, $tables) !== []) {
            throw new RuntimeException('Cleanup ownership is incomplete or includes pre-existing tables. Keep the data.');
        }
        foreach (array_diff($existing, $tables) as $table) {
            foreach ($db->getForeignKeyData($table) as $key) {
                if (in_array($key->foreign_table_name, $tables, true)) {
                    throw new RuntimeException('Other data references this module: ' . $table . '. Keep the data.');
                }
            }
        }
        return $contract['tables'];
    }
}
