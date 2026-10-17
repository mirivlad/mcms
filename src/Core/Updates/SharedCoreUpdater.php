<?php

declare(strict_types=1);

namespace MCMS\Core\Updates;

use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Install\SharedHostSetup;
use MCMS\Core\Version;
use RuntimeException;
use Throwable;
use ZipArchive;

/** ZIP-only code activation, with a standalone pre-bootstrap crash recovery gate. */
final class SharedCoreUpdater
{
    public const ROOTS = ['app', 'src', 'modules', 'themes', 'catalog', 'vendor', 'public_html/assets'];
    private const MAX_ZIP = 120 * 1024 * 1024;
    private const MAX_UNPACKED = 450 * 1024 * 1024;
    private const MAX_FILES = 16000;

    private string $dir;

    public function __construct(private readonly BackupManager $backups)
    {
        $this->dir = WRITEPATH . 'backups/shared-core-updates';
        if (! is_dir($this->dir) && ! mkdir($this->dir, 0700, true) && ! is_dir($this->dir)) {
            throw new RuntimeException('Cannot create private Core update directory.');
        }
    }

    public function active(): ?array
    {
        $path = $this->dir . '/active.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($data) ? $data : null;
    }

    public function last(): ?array
    {
        $path = $this->dir . '/last.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        return is_array($data) ? $data : null;
    }

    public function prepare(string $archive): array
    {
        $this->assertShared();
        if ($this->active() !== null) {
            throw new RuntimeException('A Core update or recovery is in progress.');
        }
        $version = $this->inspect($archive);
        $token = bin2hex(random_bytes(16));
        $dir = $this->dir . '/pending';
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot stage Core package.');
        }
        $target = $dir . '/' . $token . '.zip';
        if (! copy($archive, $target) || ! chmod($target, 0600)) {
            throw new RuntimeException('Cannot preserve uploaded Core package.');
        }
        try {
            $state = [
                'token' => $token,
                'version' => $version,
                'from' => Version::VERSION,
                'sha256' => hash_file('sha256', $target),
                'created_at' => time(),
                'status' => 'prepared',
            ];
            self::write($this->dir . '/pending/' . $token . '.json', $state);
            return $state;
        } catch (Throwable $e) {
            @unlink($target);
            throw $e;
        }
    }

    /** Strictly higher patch release, validated structure and hash of every code file. */
    public function inspect(string $path): string
    {
        if (! is_file($path) || is_link($path) || filesize($path) > self::MAX_ZIP) {
            throw new RuntimeException('Core ZIP is missing or exceeds the 120 MiB limit.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Core ZIP cannot be opened.');
        }
        try {
            $manifestText = $zip->getFromName('shared-release.json');
            if (! is_string($manifestText) || strlen($manifestText) > 4 * 1024 * 1024) {
                throw new RuntimeException('Core ZIP has no shared release manifest.');
            }
            $manifest = json_decode($manifestText, true, 512, JSON_THROW_ON_ERROR);
            $version = $manifest['version'] ?? null;
            if (($manifest['format'] ?? null) !== 'mcms-shared-release-v1' || ! is_string($version)
                || ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version)
                || ! is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('Invalid release manifest.');
            }
            $old = explode('.', Version::VERSION);
            $next = explode('.', $version);
            if ($old[0] !== $next[0] || $old[1] !== $next[1] || (int) $next[2] <= (int) $old[2]) {
                throw new RuntimeException('Only newer same-minor patch Core releases can be installed automatically.');
            }
            $present = [];
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! is_array($stat)) {
                    throw new RuntimeException('Unreadable Core ZIP member.');
                }
                $name = $stat['name'];
                if (str_ends_with($name, '/')) {
                    $name = substr($name, 0, -1);
                    if (! self::validPath($name)) {
                        throw new RuntimeException('Unsafe Core ZIP directory.');
                    }
                    continue;
                }
                if (isset($present[$name]) || ! self::validPath($name)) {
                    throw new RuntimeException('Duplicate or unsafe Core ZIP path.');
                }
                if ($zip->getExternalAttributesIndex($i, $os, $mode) && $os === ZipArchive::OPSYS_UNIX) {
                    $type = ($mode >> 16) & 0170000;
                    if ($type !== 0 && $type !== 0100000) {
                        throw new RuntimeException('Links and special files are forbidden in Core packages.');
                    }
                }
                $present[$name] = true;
                $bytes += $stat['size'];
                if ($bytes > self::MAX_UNPACKED || count($present) > self::MAX_FILES) {
                    throw new RuntimeException('Unpacked Core ZIP exceeds safety limits.');
                }
                if (self::isCode($name)) {
                    $expected = $manifest['files'][$name] ?? null;
                    if (! is_string($expected) || preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1) {
                        throw new RuntimeException('Core file missing from release manifest.');
                    }
                    $stream = $zip->getStream($name);
                    if ($stream === false) {
                        throw new RuntimeException('Cannot read Core ZIP member.');
                    }
                    $hash = hash_init('sha256');
                    hash_update_stream($hash, $stream);
                    fclose($stream);
                    if (! hash_equals($expected, hash_final($hash))) {
                        throw new RuntimeException('Core ZIP checksum mismatch: ' . $name);
                    }
                }
            }
            foreach ($manifest['files'] as $name => $digest) {
                if (! isset($present[$name]) || ! self::isCode($name)) {
                    throw new RuntimeException('Core manifest contains a nonexistent or unsupported file.');
                }
            }
            $versionSource = $zip->getFromName('src/Core/Version.php');
            if (! is_string($versionSource) || ! preg_match(
                '/const VERSION\s*=\s*\'([0-9]+\.[0-9]+\.[0-9]+)\'/', $versionSource, $match
            ) || $match[1] !== $version) {
                throw new RuntimeException('Core ZIP metadata and PHP Version::VERSION disagree.');
            }
            foreach (['app/Config/Paths.php', 'src/Core/Version.php', 'vendor/autoload.php',
                'public_html/assets/vendor/bootstrap/css/bootstrap.min.css'] as $required) {
                if (! isset($present[$required])) {
                    throw new RuntimeException('Core release is incomplete: ' . $required);
                }
            }
            return $version;
        } finally {
            $zip->close();
        }
    }

    public function apply(string $token): void
    {
        $this->assertShared();
        $this->token($token);
        $lock = fopen($this->dir . '/core.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another Core update is running.');
        }
        try {
            if ($this->active() !== null) {
                throw new RuntimeException('Core update already active.');
            }
            $state = self::read($this->dir . '/pending/' . $token . '.json');
            $zipFile = $this->dir . '/pending/' . $token . '.zip';
            if (($state['status'] ?? null) !== 'prepared' || time() - ($state['created_at'] ?? 0) > 3600
                || ! is_file($zipFile) || ! hash_equals($state['sha256'], (string) hash_file('sha256', $zipFile))
                || $this->inspect($zipFile) !== $state['version']) {
                throw new RuntimeException('Core package preview expired or changed.');
            }
            // Data+SQL safety is recorded BEFORE the first code directory moves.
            $safety = $this->backups->create();
            $stage = $this->dir . '/stage-' . $token;
            if (! mkdir($stage, 0700)) {
                throw new RuntimeException('Cannot allocate Core staging.');
            }
            $this->extractCode($zipFile, $stage);
            foreach (self::ROOTS as $relative) {
                $old = ROOTPATH . $relative;
                $new = $stage . '/' . $relative;
                if (! is_dir($old) || ! is_dir($new) || stat($old)['dev'] !== stat($new)['dev']) {
                    throw new RuntimeException('Core directories require same-filesystem atomic rename support.');
                }
            }
            $state['status'] = 'swapping';
            $state['safety'] = $safety;
            $state['started_at'] = time();
            self::write($this->dir . '/active.json', $state);
            $previousRoot = $this->dir . '/previous-' . $token;
            mkdir($previousRoot, 0700);
            foreach (self::ROOTS as $relative) {
                $old = ROOTPATH . $relative;
                $previous = $previousRoot . '/' . $relative;
                if (! is_dir(dirname($previous)) && ! mkdir(dirname($previous), 0700, true)) {
                    throw new RuntimeException('Cannot create previous Core directory.');
                }
                if (! rename($old, $previous) || ! rename($stage . '/' . $relative, $old)) {
                    throw new RuntimeException('Cannot activate Core code. Recovery will restore old directories.');
                }
            }
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $state['status'] = 'swapped';
            $state['swapped_at'] = time();
            self::write($this->dir . '/active.json', $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function finish(): void
    {
        $this->assertShared();
        $lock = fopen($this->dir . '/core.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Core updater is busy.');
        }
        try {
            $state = $this->active();
            if (($state['status'] ?? null) !== 'swapped') {
                throw new RuntimeException('No staged Core upgrade to complete.');
            }
            $state['status'] = 'finishing';
            self::write($this->dir . '/active.json', $state);
            // Fresh request: old Core PHP classes are no longer loaded.
            $migrations = \Config\Services::migrations();
            foreach (['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'] as $namespace) {
                $migrations->setNamespace($namespace)->latest();
            }
            service('mcmsAccess')->syncCoreDefinitions();
            service('mcmsModules')->updateEnabled();
            $state['status'] = 'completed';
            self::write($this->dir . '/last.json', $state);
            unlink($this->dir . '/active.json');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function extractCode(string $archive, string $stage): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open staged Core package.');
        }
        try {
            foreach (self::ROOTS as $relative) {
                mkdir($stage . '/' . $relative, 0700, true);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || str_ends_with($name, '/') || ! self::isCode($name)) {
                    continue;
                }
                $dest = $stage . '/' . $name;
                $parent = dirname($dest);
                if (! is_dir($parent) && ! mkdir($parent, 0700, true)) {
                    throw new RuntimeException('Cannot create Core staging directory.');
                }
                $in = $zip->getStream($name);
                $out = fopen($dest, 'xb');
                if ($in === false || $out === false) {
                    throw new RuntimeException('Cannot stage Core file.');
                }
                try {
                    if (stream_copy_to_stream($in, $out) === false) {
                        throw new RuntimeException('Cannot extract Core file.');
                    }
                } finally {
                    fclose($in);
                    fclose($out);
                }
                if (str_ends_with($name, '.php')) {
                    try {
                        token_get_all((string) file_get_contents($dest), TOKEN_PARSE);
                    } catch (\ParseError $error) {
                        throw new RuntimeException('Candidate Core PHP syntax failed: ' . $name, 0, $error);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function isCode(string $name): bool
    {
        foreach (self::ROOTS as $root) {
            if (str_starts_with($name, $root . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function validPath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\\") || str_contains($name, "\0")
            || str_starts_with($name, '/') || str_contains($name, '//')) {
            return false;
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return self::isCode($name) || in_array($name, ['.env', 'composer.json', 'composer.lock',
            'LICENSE', 'THIRD_PARTY_NOTICES.md', 'README-SHARED-HOSTING.md', 'public_html',
            'public_html/.htaccess', 'public_html/index.php', 'public_html/robots.txt',
            'shared-core-rescue.php', 'shared-release.json', 'writable', 'writable/index.html'], true)
            || in_array($name, self::ROOTS, true) || str_starts_with($name, 'writable/');
    }

    private function token(string $value): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $value) !== 1) {
            throw new RuntimeException('Invalid Core update token.');
        }
    }

    private function assertShared(): void
    {
        if (! SharedHostSetup::enabled()) {
            throw new RuntimeException('Shared Core ZIP updating is disabled outside shared-hosting mode.');
        }
    }

    public static function write(string $file, array $value): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, json_encode($value, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || ! rename($tmp, $file)) {
            throw new RuntimeException('Cannot persist Core update state.');
        }
    }

    public static function read(string $file): array
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data)) {
            throw new RuntimeException('Core update receipt is missing.');
        }
        return $data;
    }
}
