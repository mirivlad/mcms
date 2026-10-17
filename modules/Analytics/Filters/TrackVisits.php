<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use MCMS\Modules\Analytics\Privacy\Consent;

final class TrackVisits implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || $request->getMethod() !== 'GET'
            || $response->getStatusCode() !== 200 || ! str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')
            || $request->isAJAX() || $request->getHeaderLine('DNT') === '1' || $request->getHeaderLine('Sec-GPC') === '1'
            || str_contains(strtolower($request->getHeaderLine('Purpose') . $request->getHeaderLine('Sec-Purpose')), 'prefetch')) {
            return;
        }
        $base = rtrim((string) parse_url(config('App')->baseURL, PHP_URL_PATH), '/');
        $path = $request->getUri()->getPath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . ltrim($path, '/');
        $first = strtolower(rawurldecode(explode('/', ltrim($path, '/'))[0]));
        if (strlen($path) > 500 || in_array($first, [
            'admin', 'account', 'login', 'logout', 'register', 'auth', 'install', '_health',
            'assets', 'theme-assets', 'api', 'cart', 'checkout', 'captcha', 'analytics',
        ], true)) {
            return;
        }
        $agent = $request->getHeaderLine('User-Agent');
        if ($agent === '' || preg_match('/bot|spider|crawler|slurp|headless|preview|curl|wget|python|monitor|healthcheck/i', $agent)) {
            return;
        }

        // No identifier, analytics row or tracking cookie is created before a
        // visitor explicitly opts in. Existing DNT/GPC exclusions stay in force.
        $choice = $request->getCookie(Consent::COOKIE);
        if (! Consent::permitted($choice)) {
            if (Consent::undecided($choice)) {
                $this->appendPrivacyNotice($response);
            }
            return;
        }

        try {
            $token = static fn ($value): string => is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1
                ? $value : bin2hex(random_bytes(16));
            $visitor = $token($request->getCookie('mcms_analytics_visitor'));
            $visit = $token($request->getCookie('mcms_analytics_visit'));
            $host = strtolower($request->getUri()->getHost());
            $source = strtolower((string) parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST));
            // Only an external hostname is retained: never credentials, a path or query.
            if ($source === $host || strlen($source) > 253 || preg_match('/^(?:[a-z0-9-]+\.)*[a-z0-9-]+$/D', $source) !== 1) {
                $source = '';
            }
            $device = preg_match('/ipad|tablet|android(?!.*mobile)/i', $agent) ? 'tablet'
                : (preg_match('/mobile|iphone|ipod/i', $agent) ? 'mobile' : 'desktop');
            $visit = service('mcmsModuleServices')->get('analytics.statistics')->record($visitor, $visit, $path, $source, $device, time());
            foreach (['visitor' => [$visitor, 365 * 86400], 'visit' => [$visit, 1800]] as $name => [$value, $expiry]) {
                $response->setCookie('mcms_analytics_' . $name, $value, $expiry, '', ($base ?: '') . '/', '',
                    config('Cookie')->secure, true, 'Lax');
            }
            $response->setHeader('Cache-Control', 'private, no-store');
        } catch (\Throwable $e) {
            // Statistics must never prevent a public page from being served.
            log_message('error', 'Analytics recording failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function appendPrivacyNotice(ResponseInterface $response): void
    {
        $body = $response->getBody();
        if (! is_string($body) || strlen($body) > 2_000_000 || stripos($body, '</body>') === false) {
            return;
        }
        $text = esc(lang('Analytics.privacyNotice'));
        $label = esc(lang('Analytics.privacySettings'));
        $url = esc(site_url('analytics/privacy'), 'attr');
        $notice = '<aside role="region" aria-label="' . $label
            . '" style="position:fixed;z-index:10000;bottom:1rem;left:1rem;right:1rem;'
            . 'max-width:48rem;margin:auto;padding:.8rem 1rem;border:1px solid #aaa;'
            . 'border-radius:.6rem;background:#fff;color:#222;box-shadow:0 4px 16px #0003">'
            . $text . ' <a href="' . $url . '">' . $label . '</a></aside>';
        $updated = preg_replace('~</body>~i', $notice . '</body>', $body, 1);
        if (is_string($updated)) {
            $response->setBody($updated)->setHeader('Cache-Control', 'private, no-store');
        }
    }
}
