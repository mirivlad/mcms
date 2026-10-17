<?php

declare(strict_types=1);

namespace MCMS\Core\Updates;

use MCMS\Core\Backup\BackupManager;
use MCMS\Core\Packages\PackageState;
use MCMS\Core\Version;
use RuntimeException;

/**
 * Optional Portainer CE Git-stack redeploy. Credentials only exist in the updater
 * sidecar environment, never in the web-facing PHP-FPM container or admin forms.
 */
final class CoreUpdater
{
    public function __construct(
        private readonly UpdatePolicy $policy,
        private readonly BackupManager $backups,
    ) {}

    public function run(): ?array
    {
        if (! is_cli()) {
            throw new RuntimeException('Core updates require a CLI worker.');
        }
        if (! $this->policy->get()['core']) {
            return null;
        }
        $url = trim((string) getenv('MCMS_PORTAINER_URL'));
        $key = (string) getenv('MCMS_PORTAINER_API_KEY');
        $stack = (string) getenv('MCMS_PORTAINER_STACK_ID');
        $endpoint = (string) getenv('MCMS_PORTAINER_ENDPOINT_ID');
        if (! self::validConfiguration($url, $key, $stack, $endpoint)) {
            $this->policy->record(['type' => 'core', 'result' => 'skipped', 'reason' => 'Portainer API is not configured with verified HTTPS and numeric IDs']);
            return null;
        }

        $release = $this->request('GET', 'https://api.github.com/repos/mirivlad/mcms/releases/latest');
        $data = json_decode($release, true, 32, JSON_THROW_ON_ERROR);
        $tag = $data['tag_name'] ?? null;
        if (! is_string($tag) || preg_match('/^v(\d+)\.(\d+)\.(\d+)$/D', $tag, $matches) !== 1
            || ($data['draft'] ?? true) !== false || ($data['prerelease'] ?? true) !== false) {
            throw new RuntimeException('GitHub latest release is not a stable semantic-version release.');
        }
        // GitHub releases are published before their images are built. This
        // final artifact is uploaded only after BOTH GHCR builds succeed.
        $ready = false;
        foreach (($data['assets'] ?? []) as $asset) {
            if (($asset['name'] ?? null) === 'mcms-images-ready.json' && ($asset['state'] ?? null) === 'uploaded') {
                $ready = true;
            }
        }
        if (! $ready) {
            return null;
        }
        $target = substr($tag, 1);
        $pendingPath = $this->policy->root() . '/core-pending.json';
        $pending = PackageState::read($pendingPath);
        if (is_array($pending) && version_compare(Version::VERSION, (string) ($pending['target'] ?? '0.0.0'), '>=')) {
            PackageState::write($pendingPath, [...$pending, 'status' => 'completed', 'completed_at' => gmdate(DATE_ATOM)]);
            $this->policy->record(['type' => 'core', 'result' => 'updated', 'version' => Version::VERSION]);
        }
        if (version_compare($target, Version::VERSION, '<=')) {
            return null;
        }
        // Only patch releases may upgrade unattended. Minor and major releases
        // may include schema changes and require operator review.
        $current = explode('.', Version::VERSION);
        if ($matches[1] !== $current[0] || $matches[2] !== $current[1]) {
            $this->policy->record(['type' => 'core', 'result' => 'skipped', 'reason' => 'Major/minor upgrade needs manual review', 'version' => $target]);
            return null;
        }
        $pending = PackageState::read($pendingPath);
        if (is_array($pending) && ($pending['target'] ?? null) === $target && ($pending['status'] ?? null) === 'requested') {
            // A restart can interrupt HTTP while Portainer is recreating this
            // very sidecar. Prevent duplicate redeploy requests.
            return $pending;
        }

        $base = rtrim($url, '/');
        $inspect = json_decode($this->request('GET', $base . '/api/stacks/' . $stack, $key), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($inspect) || (int) ($inspect['Id'] ?? 0) !== (int) $stack
            || (int) ($inspect['EndpointId'] ?? 0) !== (int) $endpoint
            || ($inspect['EntryPoint'] ?? null) !== 'portainer-release-stack.yml') {
            throw new RuntimeException('Portainer stack mismatch: use the verified release stack.');
        }
        $hasStable = false;
        foreach (($inspect['Env'] ?? []) as $env) {
            if (($env['name'] ?? null) === 'MCMS_IMAGE_TAG' && ($env['value'] ?? null) === 'stable') {
                $hasStable = true;
            }
        }
        if (! $hasStable) {
            throw new RuntimeException('Portainer stack must explicitly set MCMS_IMAGE_TAG=stable.');
        }
        // PullImage would otherwise also repull the floating MariaDB tag.
        // Require a digest-pinned database image before *any* automatic redeploy.
        $databasePinned = false;
        foreach (($inspect['Env'] ?? []) as $env) {
            if (($env['name'] ?? null) === 'MCMS_DB_IMAGE'
                && preg_match('#^mariadb(?::[0-9.]+)?@sha256:[a-f0-9]{64}$#D', (string) ($env['value'] ?? '')) === 1) {
                $databasePinned = true;
            }
        }
        if (! $databasePinned) {
            throw new RuntimeException('Pin MCMS_DB_IMAGE to an immutable MariaDB digest before unattended Core updates.');
        }
        if (is_file(WRITEPATH . 'backups/restore-active.json') || service('mcmsModuleRuntime')->maintenance() !== null) {
            throw new RuntimeException('Core upgrade postponed: maintenance or restore is active.');
        }

        $backup = basename($this->backups->create());
        PackageState::write($pendingPath, [
            'target' => $target, 'from' => Version::VERSION, 'backup' => $backup,
            'requested_at' => gmdate(DATE_ATOM), 'status' => 'requested',
        ]);
        $this->policy->record(['type' => 'core', 'result' => 'requested', 'version' => $target, 'backup' => $backup]);
        $requestUrl = $base . '/api/stacks/' . $stack . '/git/redeploy?endpointId=' . $endpoint;
        // CE-compatible route. Do not change Git references, environment or prune services.
        try {
            $this->request('PUT', $requestUrl, $key, '{"PullImage":true,"RepullImageAndRedeploy":true,"Prune":false}');
        } catch (\Throwable $e) {
            // A network interruption may actually mean Portainer replaced our own
            // container. Keep the durable receipt for operator inspection.
            $this->policy->record(['type' => 'core', 'result' => 'unknown', 'version' => $target, 'reason' => 'Redeploy result not confirmed']);
            throw $e;
        }
        return ['target' => $target, 'backup' => $backup, 'status' => 'requested'];
    }

    public static function validConfiguration(string $url, string $key, string $stack, string $endpoint): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null) && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && str_starts_with($key, 'ptr_') && strlen($key) > 12
            && ctype_digit($stack) && (int) $stack > 0 && ctype_digit($endpoint) && (int) $endpoint > 0;
    }

    private function request(string $method, string $url, ?string $key = null, ?string $body = null): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Cannot initialize updater HTTPS client.');
        }
        $headers = ['Accept: application/json', 'User-Agent: mCMS-updater/' . Version::VERSION];
        if ($key !== null) {
            $headers[] = 'X-API-Key: ' . $key;
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (! is_string($response) || $http < 200 || $http >= 300 || strlen($response) > 256000) {
            throw new RuntimeException('Updater HTTPS request failed (HTTP ' . $http . ').');
        }
        return $response;
    }
}
