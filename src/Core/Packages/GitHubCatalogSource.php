<?php

declare(strict_types=1);

namespace MCMS\Core\Packages;

use RuntimeException;

/** No credentials, arbitrary hosts, automatic redirects or unbounded bodies. */
final class GitHubCatalogSource
{
    public const BASE = 'https://raw.githubusercontent.com/mirivlad/mcms/main/catalog/';

    public static function assertUrl(string $url, bool $release = false): void
    {
        $raw = '#^https://raw\.githubusercontent\.com/mirivlad/mcms/main/catalog/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+$#D';
        $asset = '#^https://github\.com/mirivlad/mcms/releases/download/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\.zip$#D';
        if (str_contains($url, '..') || (! preg_match($raw, $url) && (! $release || ! preg_match($asset, $url)))) {
            throw new RuntimeException('Catalog URL must use this project\'s trusted GitHub HTTPS sources.');
        }
    }

    public function fetch(string $url, int $limit): string
    {
        self::assertUrl($url, true);
        if (! function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for the GitHub catalog. Cached data and manual ZIP management remain available.');
        }
        $allowAssetRedirect = str_starts_with($url, 'https://github.com/mirivlad/mcms/releases/download/');
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $host = parse_url($url, PHP_URL_HOST);
            $addresses = gethostbynamel($host);
            if (! is_array($addresses) || $addresses === []) {
                throw new RuntimeException('GitHub DNS is unavailable. Cached catalog remains available.');
            }
            foreach ($addresses as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new RuntimeException('GitHub resolved to a non-public address.');
                }
            }
            $body = '';
            $location = null;
            $tooLarge = false;
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '',
                CURLOPT_RESOLVE => [$host . ':443:' . implode(',', $addresses)],
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'mCMS-extension-catalog', CURLOPT_HTTPHEADER => ['Accept: application/octet-stream'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
                    if (str_starts_with(strtolower($line), 'location:')) {
                        $location = trim(substr($line, 9));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $limit): int {
                    if (strlen($body) + strlen($chunk) > $limit) {
                        $tooLarge = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($tooLarge) {
                throw new RuntimeException('Catalog download exceeds the permitted size.');
            }
            if ($ok === false) {
                throw new RuntimeException('Cannot contact GitHub: ' . curl_error($curl));
            }
            if ($status === 200) {
                return $body;
            }
            if ($allowAssetRedirect && in_array($status, [301, 302, 303, 307, 308], true)
                && is_string($location) && strlen($location) < 8192
                && preg_match('#^https://release-assets\.githubusercontent\.com/[A-Za-z0-9_./-]+\?[^\s\\\\]+$#D', $location)
                && ! str_contains(parse_url($location, PHP_URL_PATH), '..')) {
                $url = $location;
                continue;
            }
            throw new RuntimeException('GitHub catalog HTTP ' . $status . '. The repository may be private or temporarily unavailable. Cached data is retained.');
        }
        throw new RuntimeException('Too many GitHub asset redirects.');
    }
}
