<?php

declare(strict_types=1);

namespace MCMS\Core\Backup;

use MCMS\Core\Install\SharedHostSetup;
use mysqli;
use RuntimeException;

/**
 * Restricted-host backup driver. Own structured database format, one record per line;
 * refuse unsupported database constructs rather than omit application data.
 *
 * Only used for archives created in shared-hosting mode. This intentionally
 * does not try to interpret arbitrary mysqldump dialects.
 */
final class PhpDatabaseTransfer
{
    private const HEADER = '-- mCMS PHP database backup v1';

    public static function connection(): mysqli
    {
        $host = (string) (getenv('MCMS_DB_HOST') ?: '');
        $user = (string) (getenv('MCMS_DB_USER') ?: '');
        $database = (string) (getenv('MCMS_DB_NAME') ?: '');
        $password = SharedHostSetup::databasePassword();
        $port = (int) (getenv('MCMS_DB_PORT') ?: '3306');
        if ($host === '' || $user === '' || $database === '' || $password === '') {
            throw new RuntimeException('MySQL connection is not configured.');
        }
        try {
            $db = new mysqli($host, $user, $password, $database, $port);
            $db->set_charset('utf8mb4');
            return $db;
        } catch (\mysqli_sql_exception $e) {
            throw new RuntimeException('Cannot connect to the configured database.', 0, $e);
        }
    }

    public static function dump(string $destination): void
    {
        $file = fopen($destination, 'wb');
        if ($file === false) {
            throw new RuntimeException('Cannot write PHP database export.');
        }
        $db = self::connection();
        try {
            $db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            self::write($file, self::HEADER . "\n");
            $tables = $db->query('SHOW FULL TABLES')->fetch_all(MYSQLI_NUM);
            foreach ($tables as $row) {
                if ($row[1] !== 'BASE TABLE') {
                    throw new RuntimeException('PHP exporter cannot safely preserve database views.');
                }
            }
            $triggers = $db->query('SHOW TRIGGERS');
            if ($triggers !== false && $triggers->num_rows !== 0) {
                throw new RuntimeException('PHP exporter cannot safely preserve database triggers.');
            }
            $routines = $db->query(
                "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() LIMIT 1"
            );
            if ($routines !== false && $routines->num_rows !== 0) {
                throw new RuntimeException('PHP exporter cannot safely preserve stored routines.');
            }
            foreach ($tables as $row) {
                $name = (string) $row[0];
                $identifier = self::quoteIdentifier($name);
                $definition = $db->query('SHOW CREATE TABLE ' . $identifier)->fetch_assoc();
                if (! is_array($definition) || ! isset($definition['Create Table'])) {
                    throw new RuntimeException('Cannot retrieve database schema.');
                }
                // Binary-safe base64 line records avoid SQL parser edge cases in the
                // DDL, including escaped semicolons and literal newlines.
                self::write($file, 'DDL ' . base64_encode($definition['Create Table']) . "\n");
                $rows = $db->query('SELECT * FROM ' . $identifier, MYSQLI_USE_RESULT);
                if ($rows === false) {
                    throw new RuntimeException('Cannot stream database table.');
                }
                try {
                    while ($record = $rows->fetch_assoc()) {
                        $values = [];
                        foreach ($record as $column => $value) {
                            $values[$column] = $value === null ? null : base64_encode((string) $value);
                        }
                        $payload = json_encode(
                            ['table' => $name, 'values' => $values],
                            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                        );
                        // Binary values are already base64; JSON contains ASCII only.
                        self::write($file, 'ROW ' . $payload . "\n");
                    }
                } finally {
                    $rows->free();
                }
            }
            $db->query('COMMIT');
        } catch (\Throwable $e) {
            $db->query('ROLLBACK');
            throw $e;
        } finally {
            fclose($file);
            $db->close();
        }
    }

    public static function assertArchiveFormat(string $archive): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot inspect verified backup.');
        }
        try {
            $handle = $zip->getStream('database.sql');
            if ($handle === false) {
                throw new RuntimeException('Backup has no database payload.');
            }
            try {
                $header = rtrim((string) fgets($handle, 128), "\r\n");
            } finally {
                fclose($handle);
            }
            if ($header !== self::HEADER) {
                throw new RuntimeException('This backup contains a Docker/CLI database export. Shared-hosting restore requires an mCMS PHP export from shared-hosting mode.');
            }
        } finally {
            $zip->close();
        }
    }

    public static function isNative(string $path): bool
    {
        $file = fopen($path, 'rb');
        if ($file === false) {
            return false;
        }
        try {
            return rtrim((string) fgets($file), "\r\n") === self::HEADER;
        } finally {
            fclose($file);
        }
    }

    public static function import(string $path): void
    {
        if (! self::isNative($path)) {
            throw new RuntimeException('Shared hosting can only import native mCMS PHP database dumps. Restore Docker-generated SQL with database CLI tools on a compatible server.');
        }
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('Cannot read verified PHP database dump.');
        }
        $db = self::connection();
        try {
            $db->query('SET FOREIGN_KEY_CHECKS=0');
            $db->query('SET SESSION sql_mode = ""');
            // Called only from the verified mCMS backup restore protocol.
            // Reject unexpected payloads before any destructive action.
            $defs = [];
            $allowedTables = [];
            fgets($file);
            // Complete validation pass before the first DROP. A malformed or
            // oversized record cannot begin a partially destructive restore.
            while (($line = fgets($file, 32 * 1024 * 1024 + 1)) !== false) {
                if (! str_ends_with($line, "\n") || strlen($line) > 32 * 1024 * 1024) {
                    throw new RuntimeException('Oversized or truncated database record.');
                }
                if (str_starts_with($line, 'DDL ')) {
                    $definition = base64_decode(trim(substr($line, 4)), true);
                    if (! is_string($definition) ||
                        preg_match('/^CREATE TABLE `((?:[^`]|(?:``))+)` /', $definition, $match) !== 1) {
                        throw new RuntimeException('Invalid SQL schema entry.');
                    }
                    $table = str_replace('``', '`', $match[1]);
                    self::quoteIdentifier($table);
                    if (isset($allowedTables[$table])) {
                        throw new RuntimeException('Duplicate table definition in backup.');
                    }
                    $allowedTables[$table] = true;
                    $defs[] = $definition;
                } elseif (str_starts_with($line, 'ROW ')) {
                    $data = json_decode(substr($line, 4), true, 512, JSON_THROW_ON_ERROR);
                    self::validateRow($data);
                    if (strlen($line) > 32 * 1024 * 1024) {
                        throw new RuntimeException('Database row exceeds web import limit.');
                    }
                    // ROW records only follow the corresponding CREATE TABLE.
                    if (! isset($allowedTables[$data['table']])) {
                        throw new RuntimeException('Row for unknown database table.');
                    }
                } else {
                    throw new RuntimeException('Unrecognized PHP database export record.');
                }
            }
            if ($defs === []) {
                throw new RuntimeException('Database export has no schema.');
            }
            $existing = $db->query('SHOW FULL TABLES');
            $tables = $existing->fetch_all(MYSQLI_NUM);
            foreach ($tables as $row) {
                if ($row[1] === 'VIEW') {
                    $db->query('DROP VIEW ' . self::quoteIdentifier((string) $row[0]));
                } else {
                    $db->query('DROP TABLE ' . self::quoteIdentifier((string) $row[0]));
                }
            }
            foreach ($defs as $definition) {
                $db->query($definition);
            }
            rewind($file);
            fgets($file);
            while (($line = fgets($file, 32 * 1024 * 1024 + 1)) !== false) {
                if (! str_starts_with($line, 'ROW ')) {
                    continue;
                }
                $data = json_decode(substr($line, 4), true, 512, JSON_THROW_ON_ERROR);
                $names = [];
                $values = [];
                foreach ($data['values'] as $name => $value) {
                    $names[] = self::quoteIdentifier($name);
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $bytes = base64_decode($value, true);
                        if ($bytes === false) {
                            throw new RuntimeException('Invalid import cell.');
                        }
                        $values[] = "'" . $db->real_escape_string($bytes) . "'";
                    }
                }
                $db->query('INSERT INTO ' . self::quoteIdentifier($data['table']) .
                    ' (' . implode(',', $names) . ') VALUES (' . implode(',', $values) . ')');
            }
        } finally {
            $db->query('SET FOREIGN_KEY_CHECKS=1');
            $db->close();
            fclose($file);
        }
    }

    private static function validateRow(mixed $data): void
    {
        if (! is_array($data) || ! is_string($data['table'] ?? null)
            || ! is_array($data['values'] ?? null) || $data['values'] === []) {
            throw new RuntimeException('Malformed PHP database row.');
        }
        self::quoteIdentifier($data['table']);
        foreach ($data['values'] as $name => $value) {
            if (! is_string($name) || (! is_string($value) && $value !== null)) {
                throw new RuntimeException('Invalid import column.');
            }
            self::quoteIdentifier($name);
            if ($value !== null && base64_decode($value, true) === false) {
                throw new RuntimeException('Invalid import cell.');
            }
        }
    }

    public static function quoteIdentifier(string $value): string
    {
        if ($value === '' || strlen($value) > 64 || str_contains($value, "\0")) {
            throw new RuntimeException('Invalid MySQL identifier.');
        }
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /** @param resource $file */
    private static function write($file, string $chunk): void
    {
        if (fwrite($file, $chunk) !== strlen($chunk)) {
            throw new RuntimeException('Database export write failed.');
        }
    }
}
