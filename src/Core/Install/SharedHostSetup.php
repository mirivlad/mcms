<?php

declare(strict_types=1);

namespace MCMS\Core\Install;

use Config\Services;
use RuntimeException;

/**
 * First-run only; Docker installs continue using their existing startup CLI.
 * Credentials are written outside DocumentRoot and never echoed back.
 */
final class SharedHostSetup
{
    public static function enabled(): bool
    {
        return getenv('MCMS_INSTALL_MODE') === 'shared';
    }

    public static function configured(): bool
    {
        return trim((string) getenv('MCMS_DB_HOST')) !== ''
            && trim((string) getenv('MCMS_DB_NAME')) !== ''
            && trim((string) getenv('MCMS_DB_USER')) !== ''
            && self::databasePassword() !== '';
    }

    public static function databasePassword(): string
    {
        $encoded = getenv('MCMS_DB_PASSWORD_B64');
        if (self::enabled() && is_string($encoded) && $encoded !== '') {
            return base64_decode($encoded, true) ?: '';
        }
        return (string) (getenv('MCMS_DB_PASSWORD') ?: '');
    }

    /** @return array<string,string> Non-sensitive preflight failures. */
    public static function requirements(): array
    {
        $errors = [];
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $errors['php'] = 'PHP 8.2 or newer is required.';
        }
        foreach (['mysqli', 'intl', 'mbstring', 'gd', 'curl', 'zip', 'fileinfo'] as $extension) {
            if (! extension_loaded($extension)) {
                $errors[$extension] = 'Missing PHP extension: ' . $extension;
            }
        }
        $root = realpath(ROOTPATH);
        $public = realpath(FCPATH);
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($root === false || $public === false || $root === $public
            || ! str_starts_with($public, $root . DIRECTORY_SEPARATOR)
            || ($documentRoot !== false && $documentRoot !== $public)) {
            $errors['webroot'] = 'The public_html directory must be the only web-accessible document root. Keep app, vendor, writable and .env outside it.';
        }
        if (! is_writable(ROOTPATH) || ! is_writable(WRITEPATH)
            || ! is_file(ROOTPATH . 'vendor/autoload.php')) {
            $errors['storage'] = 'Application root, writable directory and installed vendor libraries must be present and writable as appropriate.';
        }

        return $errors;
    }

    /** @param array<string,mixed> $values */
    public static function configure(array $values): void
    {
        if (! self::enabled() || self::configured()) {
            throw new RuntimeException('Database setup is already closed.');
        }
        if (self::requirements() !== []) {
            throw new RuntimeException('Hosting preflight failed. Correct the server requirements first.');
        }
        $inputs = [];
        foreach (['host', 'database', 'username', 'password'] as $key) {
            $value = (string) ($values[$key] ?? '');
            if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('Database connection details are missing or invalid.');
            }
            $inputs[$key] = $value;
        }
        $port = filter_var($values['port'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($port === false) {
            throw new RuntimeException('Database port is invalid.');
        }
        if (! extension_loaded('mysqli')) {
            throw new RuntimeException('The mysqli PHP extension is required.');
        }
        $smtpHost = trim((string) ($values['smtp_host'] ?? ''));
        $mail = [];
        if ($smtpHost !== '') {
            if (strlen($smtpHost) > 255 || preg_match('/^[a-z0-9.:-]+$/iD', $smtpHost) !== 1) {
                throw new RuntimeException('Invalid SMTP hostname.');
            }
            $smtpPort = filter_var($values['smtp_port'] ?? '587', FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            $crypto = strtolower((string) ($values['smtp_crypto'] ?? 'tls'));
            $sender = trim((string) ($values['smtp_sender'] ?? ''));
            $user = trim((string) ($values['smtp_user'] ?? ''));
            $pass = (string) ($values['smtp_password'] ?? '');
            if ($smtpPort === false || ! in_array($crypto, ['', 'tls', 'ssl'], true)
                || filter_var($sender, FILTER_VALIDATE_EMAIL) === false || strlen($user) > 255
                || strlen($pass) > 255 || str_contains($pass, "\0")) {
                throw new RuntimeException('SMTP configuration is incomplete or invalid.');
            }
            $mail = [
                'MCMS_MAIL_HOST' => $smtpHost,
                'MCMS_MAIL_PORT' => (string) $smtpPort,
                'MCMS_MAIL_FROM' => $sender,
                'MCMS_MAIL_USER' => $user,
                'MCMS_MAIL_PASSWORD_B64' => base64_encode($pass),
                'MCMS_MAIL_CRYPTO' => $crypto,
            ];
        }
        $lock = fopen(WRITEPATH . 'shared-setup.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot acquire installer lock.');
        }
        try {
            // A second concurrent HTTP request has a stale process environment;
            // inspect the file under the lock as well before replacing secrets.
            $existing = @file_get_contents(ROOTPATH . '.env');
            if (self::configured() || (is_string($existing)
                && preg_match('/^MCMS_DB_PASSWORD(?:_B64)?\s*=/m', $existing))) {
                throw new RuntimeException('Database setup is already closed.');
            }
            try {
                $db = new \mysqli($inputs['host'], $inputs['username'], $inputs['password'], $inputs['database'], $port);
                $db->set_charset('utf8mb4');
                $tables = $db->query('SHOW TABLES');
                if ($tables === false || $tables->num_rows !== 0) {
                    throw new RuntimeException('Use a new empty database for the first installation.');
                }
                $db->close();
            } catch (\mysqli_sql_exception) {
                throw new RuntimeException('Database connection failed. Check the host, database, username, password and privileges.');
            }

            $config = "CI_ENVIRONMENT = production\nMCMS_INSTALL_MODE = shared\n";
            foreach ([
                'MCMS_DB_HOST' => $inputs['host'],
                'MCMS_DB_PORT' => (string) $port,
                'MCMS_DB_NAME' => $inputs['database'],
                'MCMS_DB_USER' => $inputs['username'],
                'MCMS_DB_PASSWORD_B64' => base64_encode($inputs['password']),
            ] + $mail as $name => $value) {
                // JSON quoted strings also escape embedded quotes and backslashes.
                $config .= $name . ' = ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            }
            $config .= "MCMS_TIMEZONE = UTC\n";

            $temp = tempnam(ROOTPATH, '.mcms-env-');
            if ($temp === false) {
                throw new RuntimeException('Cannot create the private configuration file.');
            }
            try {
                chmod($temp, 0600);
                if (file_put_contents($temp, $config, LOCK_EX) !== strlen($config)
                    || ! rename($temp, ROOTPATH . '.env')) {
                    throw new RuntimeException('Unable to save database configuration.');
                }
            } finally {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Run only before superadmin creation, after a fresh request loaded .env. */
    public static function migrate(): void
    {
        if (! self::enabled() || ! self::configured()) {
            throw new RuntimeException('Database is not configured.');
        }
        $lock = fopen(WRITEPATH . 'shared-setup.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot acquire migration lock.');
        }
        try {
            if (service('mcmsInstaller')->isInstalled()) {
                return;
            }
            $migrations = Services::migrations();
            foreach (['App', 'CodeIgniter\\Settings', 'CodeIgniter\\Shield'] as $namespace) {
                $migrations->setNamespace($namespace)->latest();
            }
            service('mcmsAccess')->syncCoreDefinitions();
            service('mcmsModules')->updateEnabled();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
