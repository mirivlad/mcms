<?php

declare(strict_types=1);

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MCMS\Core\Admin\AdminRegistry;
use MCMS\Core\Modules\ModuleManager;
use MCMS\Core\Modules\ModuleServiceRegistry;
use MCMS\Modules\Analytics\Filters\TrackVisits;
use MCMS\Modules\Analytics\Services\Analytics;

final class AnalyticsModuleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = ['App', 'CodeIgniter\Settings'];
    private Analytics $analytics;
    private ModuleManager $manager;
    private AdminRegistry $admin;

    protected function setUp(): void
    {
        $this->resetServices();
        parent::setUp();
        service('language')->setLocale('en');
        $services = new ModuleServiceRegistry();
        $this->admin = new AdminRegistry();
        $this->manager = new ModuleManager($this->db, $this->admin, $services, ROOTPATH . 'modules');
        $this->manager->enable('analytics');
        $this->manager->bootEnabled();
        Services::injectMock('mcmsModuleServices', $services);
        $this->analytics = $services->get('analytics.statistics');
    }

    protected function tearDown(): void
    {
        $this->migrations->setNamespace('MCMS\Modules\Analytics')->regress(0, 'tests');
        $this->resetServices();
        parent::tearDown();
    }

    private function record(string $visitor, string $visit, string $path, int $at, string $source = '', string $device = 'desktop'): string
    {
        return $this->analytics->record(str_repeat($visitor, 32), str_repeat($visit, 32), $path, $source, $device, $at);
    }

    public function testDistinctVisitorsVisitsAttributionAndZeroDays(): void
    {
        $midnight = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        $this->record('a', 'b', '/', $midnight - 600, 'search.example', 'mobile');
        $this->record('a', 'b', '/news', $midnight + 60, 'ignored.example', 'desktop');
        // A new visit after 30 minutes; it is still the same visitor.
        $newVisit = $this->record('a', 'b', '/news', $midnight + 1860, 'other.example');
        self::assertNotSame(str_repeat('b', 32), $newVisit);
        $this->record('c', 'd', '/news', $midnight + 1870);

        $report = $this->analytics->report(gmdate('Y-m-d', $midnight - 2 * 86400), gmdate('Y-m-d', $midnight));
        self::assertSame(['views' => 4, 'visits' => 3, 'visitors' => 2], $report['totals']);
        self::assertSame([0, 1, 3], array_column($report['daily'], 'views'));
        self::assertSame([0, 1, 2], array_column($report['daily'], 'visitors'));
        self::assertSame([0, 1, 3], array_column($report['daily'], 'visits'));
        self::assertSame('/news', $report['pages'][0]['path']);
        self::assertSame(3, (int) $report['pages'][0]['views']);
        self::assertSame(['', 'other.example', 'search.example'], array_column($report['sources'], 'source'));
        self::assertSame([2, 1], array_map('intval', array_column($report['devices'], 'visits')));
        $today = $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'));
        self::assertSame(['views' => 3, 'visits' => 3, 'visitors' => 2], $today['totals']);
        $visit = $this->db->table('analytics_visits')->where('source', 'search.example')->get()->getRowArray();
        self::assertSame('mobile', $visit['device']);
        self::assertSame(hash('sha256', str_repeat('a', 32)), $visit['visitor']);
    }

    public function testRollingRetentionDeletesOldViewsButKeepsVisitCrossingBoundary(): void
    {
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        $cutoff = $today - 89 * 86400;
        $this->record('a', 'b', '/expired', $cutoff - 600);
        $this->record('a', 'b', '/retained', $cutoff + 60);
        $this->record('c', 'd', '/expired-only', $cutoff - 300);
        $this->record('e', 'f', '/today', $today);
        self::assertSame(2, $this->db->table('analytics_pageviews')->countAllResults());
        self::assertSame(2, $this->db->table('analytics_visits')->countAllResults());
        self::assertSame(['views' => 2, 'visits' => 2, 'visitors' => 2],
            $this->analytics->report(gmdate('Y-m-d', $cutoff), gmdate('Y-m-d'))['totals']);
    }

    public function testFiltersTrackOnlyEligiblePublicResponsesAndDiscardSensitiveUrlParts(): void
    {
        $request = $this->request('/news/story?token=secret');
        $request->setHeader('Referer', 'https://user:password@Search.Example/private?q=secret#fragment');
        $request->setHeader('User-Agent', 'Mozilla/5.0 (iPhone; Mobile)');
        $response = $this->response();
        (new TrackVisits())->after($request, $response);
        $view = $this->db->table('analytics_pageviews')->get()->getRowArray();
        $visit = $this->db->table('analytics_visits')->get()->getRowArray();
        self::assertSame('/news/story', $view['path']);
        self::assertSame('search.example', $visit['source']);
        self::assertSame('mobile', $visit['device']);
        self::assertStringContainsString('private', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $visitor = $response->getCookie('mcms_analytics_visitor');
        $visitCookie = $response->getCookie('mcms_analytics_visit');
        self::assertTrue($visitor->isHTTPOnly());
        self::assertSame('Lax', $visitor->getSameSite());
        self::assertSame(hash('sha256', $visitor->getValue()), $visit['visitor']);
        $next = $this->request('/');
        $next->setGlobal('cookie', ['mcms_analytics_visitor' => $visitor->getValue(), 'mcms_analytics_visit' => $visitCookie->getValue()]);
        (new TrackVisits())->after($next, $this->response());
        self::assertSame(['views' => 2, 'visits' => 1, 'visitors' => 1], $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'))['totals']);
    }

    public function testSystemPathsBotsOptOutAndNonPageRequestsAreExcluded(): void
    {
        foreach (['/admin', '/admin/analytics', '/%61dmin/settings', '/account/orders', '/login', '/install', '/_health', '/assets/test', '/api/v1/items', '/cart', '/checkout'] as $path) {
            (new TrackVisits())->after($this->request($path), $this->response());
        }
        foreach ([['DNT', '1'], ['Sec-GPC', '1'], ['Purpose', 'prefetch'], ['Sec-Purpose', 'prefetch'], ['User-Agent', 'Googlebot'], ['User-Agent', ''], ['X-Requested-With', 'XMLHttpRequest']] as [$name, $value]) {
            $request = $this->request('/');
            $request->setHeader($name, $value);
            $response = $this->response();
            (new TrackVisits())->after($request, $response);
            self::assertSame([], $response->getCookies());
        }
        foreach (['POST', 'HEAD'] as $method) {
            $request = $this->request('/');
            $request->setMethod($method);
            (new TrackVisits())->after($request, $this->response());
        }
        foreach ([302, 404, 500] as $status) {
            (new TrackVisits())->after($this->request('/'), $this->response()->setStatusCode($status));
        }
        (new TrackVisits())->after($this->request('/'), $this->response()->setContentType('application/json'));
        self::assertSame(0, $this->db->table('analytics_pageviews')->countAllResults());
    }

    public function testBasePathCookieScopeAndMalformedCookies(): void
    {
        $app = config('App');
        $previous = $app->baseURL;
        $app->baseURL = 'http://example.com/cms/';
        try {
            $request = $this->request('/cms/news');
            $request->setGlobal('cookie', ['mcms_analytics_visitor' => ['bad'], 'mcms_analytics_visit' => 'invalid']);
            $response = $this->response();
            (new TrackVisits())->after($request, $response);
            self::assertSame('/cms/', $response->getCookie('mcms_analytics_visitor')->getPath());
            self::assertSame('/news', $this->db->table('analytics_pageviews')->get()->getRowArray()['path']);
            (new TrackVisits())->after($this->request('/cms/admin/users'), $this->response());
            self::assertSame(1, $this->db->table('analytics_pageviews')->countAllResults());
        } finally {
            $app->baseURL = $previous;
        }
    }

    public function testBadDatesAreRejectedAndEmptyReportIsZeroFilled(): void
    {
        $today = gmdate('Y-m-d');
        self::assertSame(['views' => 0, 'visits' => 0, 'visitors' => 0], $this->analytics->report($today, $today)['totals']);
        foreach ([['2026-02-30', $today], ['invalid', $today], ["2026-01-01\0", $today], [$today, '2020-01-01'], ['2020-01-01', $today], [$today, '9999-01-01']] as [$from, $to]) {
            try {
                $this->analytics->report($from, $to);
                self::fail('Invalid date range accepted');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testDisablePreservesDataAndStopsRuntimeContributions(): void
    {
        $this->record('a', 'b', '/', time());
        self::assertContains('analytics', array_column($this->admin->menu(), 'id'));
        self::assertContains('analyticsVisits', config('Filters')->globals['after']);
        $this->manager->disable('analytics');
        self::assertSame(1, $this->db->table('analytics_pageviews')->countAllResults());
        $services = new ModuleServiceRegistry();
        $admin = new AdminRegistry();
        (new ModuleManager($this->db, $admin, $services, ROOTPATH . 'modules'))->bootEnabled();
        self::assertFalse($services->has('analytics.statistics'));
        self::assertNotContains('analytics', array_column($admin->menu(), 'id'));
    }

    public function testRecordingFailureDoesNotChangeThePublicResponse(): void
    {
        Services::injectMock('mcmsModuleServices', new ModuleServiceRegistry());
        $response = $this->response();
        (new TrackVisits())->after($this->request('/'), $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html><p>Page</p></html>', $response->getBody());
        self::assertSame([], $response->getCookies());
        self::assertSame(0, $this->db->table('analytics_pageviews')->countAllResults());
    }

    private function request(string $path): IncomingRequest
    {
        $request = new IncomingRequest(config('App'), new URI('http://example.com' . $path), null, new UserAgent());
        $request->setMethod('GET');
        $request->setHeader('User-Agent', 'Mozilla/5.0 Firefox/130');
        $request->setGlobal('cookie', []);
        return $request;
    }

    private function response(): Response
    {
        return (new Response(config('App')))->setStatusCode(200)->setContentType('text/html')->setBody('<html><p>Page</p></html>');
    }
}
