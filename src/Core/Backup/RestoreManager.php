<?php

declare(strict_types=1);

namespace MCMS\Core\Backup;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class RestoreManager
{
    private string $working;

    /** @param array<string,string> $roots */
    public function __construct(
        private readonly BackupManager $backups,
        private readonly string $directory,
        private readonly array $roots,
    ) {
        $this->working = $directory . '/restore';
        if (! is_dir($this->working) && ! mkdir($this->working, 0700, true) && ! is_dir($this->working)) {
            throw new RuntimeException('Cannot create private restore staging directory.');
        }
    }

    public function active(): ?array
    {
        $file = $this->directory . '/restore-active.json';
        if (! is_file($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true);
        return is_array($state) ? $state : ['token' => '', 'status' => 'invalid'];
    }

    public function state(string $token): ?array
    {
        $this->token($token);
        $file = $this->working . '/' . $token . '.json';
        if (! is_file($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true);
        return is_array($state) ? $state : null;
    }

    public function prepareExisting(string $name, string $owner): array
    {
        $file = $this->backups->file($name);
        if ($file === null) {
            throw new RuntimeException('Backup archive not found.');
        }
        return $this->prepare($file, $name, $owner);
    }

    public function prepareUpload(string $file, string $owner): array
    {
        if (! is_file($file) || filesize($file) > 1024 * 1024 * 1024) {
            throw new RuntimeException('Backup ZIP exceeds the 1 GiB upload limit.');
        }
        return $this->prepare($file, 'Uploaded ZIP', $owner);
    }

    private function prepare(string $file, string $label, string $owner): array
    {
        if ($this->active() !== null) {
            throw new RuntimeException('Another restore is in progress.');
        }
        $token = bin2hex(random_bytes(16));
        $candidate = $this->working . '/' . $token . '.zip';
        if (! copy($file, $candidate) || ! chmod($candidate, 0600)) {
            throw new RuntimeException('Cannot stage archive for restoration.');
        }
        try {
            $report = BackupArchive::inspect($candidate);
            $state = [
                'token' => $token,
                'owner' => $owner,
                'label' => $label,
                'sha256' => hash_file('sha256', $candidate),
                'created_at' => $report['created_at'],
                'files' => count($report['files']),
                'bytes' => $report['bytes'],
                'status' => 'prepared',
                'safety' => null,
                'error' => null,
                'prepared_at' => time(),
            ];
            $this->writeState($state);
            return $state;
        } catch (Throwable $e) {
            @unlink($candidate);
            throw $e;
        }
    }

    public function begin(string $token, string $owner): void
    {
        $this->token($token);
        $lock = fopen($this->directory . '/restore.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another restore is being started.');
        }
        try {
            if ($this->active() !== null) {
                throw new RuntimeException('Another restore is in progress.');
            }
            $state = $this->state($token);
            if ($state === null || $state['status'] !== 'prepared' || ! hash_equals((string) $state['owner'], $owner)
                || time() - $state['prepared_at'] > 3600) {
                throw new RuntimeException('Restore preparation expired or belongs to another account.');
            }
            $archive = $this->working . '/' . $token . '.zip';
            if (! is_file($archive) || ! hash_equals($state['sha256'], (string) hash_file('sha256', $archive))) {
                throw new RuntimeException('Prepared archive has changed.');
            }
            $state['status'] = 'queued';
            $this->writeState($state);
            // Set the maintenance flag only after active HTTP requests drain.
            // Unlike ModuleRuntime, this flag lives OUTSIDE the replaced volumes.
            service('mcmsModuleRuntime')->exclusive(function () use ($token): void {
                service('mcmsModuleRuntime')->assertAvailable();
                $this->atomicWrite($this->directory . '/restore-active.json', [
                    'token' => $token, 'status' => 'queued',
                ]);
            });
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function launch(string $token): void
    {
        $this->token($token);
        service('mcmsModuleRuntime')->release();
        $php = escapeshellarg(PHP_BINDIR . '/php');
        $spark = escapeshellarg(ROOTPATH . 'spark');
        $arg = escapeshellarg($token);
        $log = escapeshellarg($this->working . '/' . $token . '.log');
        $command = 'nohup ' . $php . ' ' . $spark . ' mcms:backup:restore ' . $arg
            . ' < /dev/null >> ' . $log . ' 2>&1 &';
        $process = proc_open(['/bin/sh', '-c', $command], [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ], $pipes, ROOTPATH);
        if (! is_resource($process) || proc_close($process) !== 0) {
            throw new RuntimeException('Restore worker did not start; run php spark mcms:backup:restore ' . $token);
        }
    }

    public function run(string $token): void
    {
        $this->token($token);
        $lock = fopen($this->directory . '/restore.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot acquire restore worker lock.');
        }
        $state = $this->state($token);
        $flag = $this->active();
        if ($state === null || ($state['status'] ?? '') !== 'queued' || ($flag['token'] ?? null) !== $token) {
            throw new RuntimeException('Restore worker has no matching prepared operation.');
        }
        $state['status'] = 'running';
        $this->writeState($state);
        $this->atomicWrite($this->directory . '/restore-active.json', ['token' => $token, 'status' => 'running']);
        $archive = $this->working . '/' . $token . '.zip';
        $swapped = [];
        $safety = null;
        try {
            if (! hash_equals($state['sha256'], (string) hash_file('sha256', $archive))) {
                throw new RuntimeException('Restore ZIP changed during execution.');
            }
            BackupArchive::inspect($archive);

            // Preserve the current site before changing a single DB table or data file.
            $safety = $this->backups->create();
            $state['safety'] = $safety;
            $this->writeState($state);

            $staged = [];
            foreach ($this->roots as $prefix => $root) {
                $this->assertRoot($prefix, $root);
                $dir = $root . '/.restore-stage-' . $token;
                if (! mkdir($dir, 0770) && ! is_dir($dir)) {
                    throw new RuntimeException('Cannot allocate restore staging.');
                }
                $staged[$prefix] = $dir;
            }
            $sql = $this->working . '/' . $token . '.sql';
            BackupArchive::extract($archive, $staged, $sql);
            foreach ($this->roots as $prefix => $root) {
                $swapped[] = $prefix;
                $this->swap($root, $staged[$prefix], $token);
            }
            $this->importSql($sql);
            $state['status'] = 'completed';
            $this->writeState($state);
            $this->clearActive($token);
            // Cleanup is deliberately non-critical after the database is
            // committed. A failure here must never roll back a good restore.
            foreach ($this->roots as $root) {
                try {
                    $this->erase($root . '/.restore-previous-' . $token);
                } catch (Throwable $cleanup) {
                    log_message('warning', 'Restore cleanup: {message}', ['message' => $cleanup->getMessage()]);
                }
            }
            @unlink($sql);
        } catch (Throwable $failure) {
            $rollbackError = null;
            try {
                foreach (array_reverse($swapped) as $prefix) {
                    $this->undoSwap($this->roots[$prefix], $token);
                }
                foreach ($this->roots as $root) {
                    $this->erase($root . '/.restore-stage-' . $token);
                }
                if ($safety !== null) {
                    $safetyFile = $this->backups->file($safety);
                    if ($safetyFile === null) {
                        throw new RuntimeException('Safety backup unexpectedly missing.');
                    }
                    $safetySql = $this->working . '/' . $token . '.safety.sql';
                    $this->extractSql($safetyFile, $safetySql);
                    $this->importSql($safetySql);
                    @unlink($safetySql);
                }
            } catch (Throwable $rollbackFailure) {
                $rollbackError = $rollbackFailure->getMessage();
            }
            $state['status'] = $rollbackError === null ? 'failed' : 'recovery_required';
            $state['error'] = $failure->getMessage() . ($rollbackError === null ? '' : '; rollback: ' . $rollbackError);
            $this->writeState($state);
            if ($rollbackError === null) {
                $this->clearActive($token);
            }
            throw new RuntimeException($state['error'], 0, $failure);
        } finally {
            @unlink($this->working . '/' . $token . '.sql');
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function extractSql(string $archive, string $sql): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open safety database archive.');
        }
        try {
            $input = $zip->getStream('database.sql');
            $output = fopen($sql, 'wb');
            if ($input === false || $output === false) {
                throw new RuntimeException('Cannot open safety database dump.');
            }
            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException('Cannot extract safety database dump.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }
        } finally {
            $zip->close();
        }
    }

    private function swap(string $root, string $staged, string $token): void
    {
        $previous = $root . '/.restore-previous-' . $token;
        if (! mkdir($previous, 0770)) {
            throw new RuntimeException('Cannot create restoration safety directory.');
        }
        foreach (scandir($root) ?: [] as $name) {
            if (in_array($name, ['.', '..', basename($previous), basename($staged)], true)) {
                continue;
            }
            if (! rename($root . '/' . $name, $previous . '/' . $name)) {
                throw new RuntimeException('Cannot save previous data files.');
            }
        }
        foreach (scandir($staged) ?: [] as $name) {
            if (in_array($name, ['.', '..'], true)) {
                continue;
            }
            if (! rename($staged . '/' . $name, $root . '/' . $name)) {
                throw new RuntimeException('Cannot install new data files.');
            }
        }
        rmdir($staged);
    }

    private function undoSwap(string $root, string $token): void
    {
        $previous = $root . '/.restore-previous-' . $token;
        if (! is_dir($previous)) {
            return;
        }
        foreach (scandir($root) ?: [] as $name) {
            if (in_array($name, ['.', '..', basename($previous), '.restore-stage-' . $token], true)) {
                continue;
            }
            $this->erase($root . '/' . $name);
        }
        foreach (scandir($previous) ?: [] as $name) {
            if (in_array($name, ['.', '..'], true)) {
                continue;
            }
            if (! rename($previous . '/' . $name, $root . '/' . $name)) {
                throw new RuntimeException('Could not revert previous file data.');
            }
        }
        rmdir($previous);
    }

    private function importSql(string $sql): void
    {
        $host = getenv('MCMS_DB_HOST') ?: 'db';
        $port = (int) (getenv('MCMS_DB_PORT') ?: '3306');
        $name = getenv('MCMS_DB_NAME') ?: 'mcms';
        $user = getenv('MCMS_DB_USER') ?: 'mcms';
        $password = getenv('MCMS_DB_PASSWORD');
        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Database credentials not configured.');
        }

        $db = new \mysqli($host, $user, $password, $name, $port);
        $db->set_charset('utf8mb4');
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $tables = $db->query('SHOW FULL TABLES');
        if ($tables === false) {
            throw new RuntimeException('Cannot enumerate database tables.');
        }
        $views = [];
        $normal = [];
        while ($row = $tables->fetch_row()) {
            $escaped = '`' . str_replace('`', '``', $row[0]) . '`';
            if ($row[1] === 'VIEW') {
                $views[] = $escaped;
            } else {
                $normal[] = $escaped;
            }
        }
        foreach ($views as $table) {
            $db->query('DROP VIEW IF EXISTS ' . $table);
        }
        foreach ($normal as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $table);
        }
        $db->close();
        $stderr = $this->working . '/import-' . bin2hex(random_bytes(8)) . '.err';
        $env = getenv();
        $env = is_array($env) ? $env : [];
        $env['MYSQL_PWD'] = $password;
        $cmd = [
            'mariadb', '--binary-mode=1', '--default-character-set=utf8mb4',
            '--host=' . $host, '--port=' . (string) $port,
            '--user=' . $user, '--', $name,
        ];
        $process = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', $stderr, 'w'],
        ], $pipes, null, $env);
        if (! is_resource($process)) {
            throw new RuntimeException('Cannot launch MariaDB import.');
        }
        $input = fopen($sql, 'rb');
        if ($input === false) {
            throw new RuntimeException('Cannot read verified database dump.');
        }
        try {
            stream_copy_to_stream($input, $pipes[0]);
        } finally {
            fclose($input);
            fclose($pipes[0]);
        }
        $status = proc_close($process);
        $error = is_file($stderr) ? trim((string) file_get_contents($stderr)) : '';
        @unlink($stderr);
        if ($status !== 0) {
            throw new RuntimeException('Database import failed: ' . substr($error, 0, 350));
        }
    }

    private function assertRoot(string $prefix, string $root): void
    {
        if (! in_array($prefix, ['modules_data', 'themes_data'], true) || ! is_dir($root)) {
            throw new RuntimeException('Invalid or missing restore storage root.');
        }
    }

    private function clearActive(string $token): void
    {
        $flag = $this->active();
        if (($flag['token'] ?? null) !== $token) {
            throw new RuntimeException('Restore maintenance changed ownership.');
        }
        unlink($this->directory . '/restore-active.json');
    }

    private function erase(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }
        if (! is_dir($path) || is_link($path)) {
            if (! unlink($path)) {
                throw new RuntimeException('Cannot delete previous file.');
            }
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($path);
    }

    private function writeState(array $state): void
    {
        $this->atomicWrite($this->working . '/' . $state['token'] . '.json', $state);
    }

    private function atomicWrite(string $path, array $state): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, json_encode($state, JSON_THROW_ON_ERROR)) === false
            || ! rename($tmp, $path)) {
            throw new RuntimeException('Cannot update restore state.');
        }
    }

    private function token(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            throw new InvalidArgumentException('Invalid restore token.');
        }
    }
}
