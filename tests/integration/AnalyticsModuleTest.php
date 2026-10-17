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
        $next->setGlobal('cookie', ['mcms_analytics_visitor' => $visitor->getValue(), 'mcms_analytics_visit' => $visitCookie->getValue(), 'mcms_analytics_consent' => 'yes']);
        (new TrackVisits())->after($next, $this->response());
        self::assertSame(['views' => 2, 'visits' => 1, 'visitors' => 1], $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'))['totals']);
    }

    public function testNoTrackingCookiesOrRowsWithoutExplicitConsent(): void
    {
        foreach ([[], ['mcms_analytics_consent' => 'no'], ['mcms_analytics_consent' => 'invalid']] as $cookies) {
            $request = $this->request('/');
            $request->setGlobal('cookie', $cookies);
            $response = $this->response()->setBody('<html><body><p>Page</p></body></html>');
            (new TrackVisits())->after($request, $response);
            self::assertSame([], $response->getCookies());
            self::assertSame(0, $this->db->table('analytics_pageviews')->countAllResults());
            $html = html_entity_decode($response->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($cookies === [] || $cookies['mcms_analytics_consent'] === 'invalid') {
                self::assertStringContainsString('analytics/privacy', $html);
            } else {
                self::assertStringNotContainsString('analytics/privacy', $html);
            }
        }
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
            $request->setGlobal('cookie', ['mcms_analytics_visitor' => ['bad'], 'mcms_analytics_visit' => 'invalid', 'mcms_analytics_consent' => 'yes']);
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

    public function testComparisonUsesEqualLengthPeriodsAndHandlesZeroBaselineAndExpiredHistory(): void
    {
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        $this->record('a', 'b', '/', $today - 3 * 86400);
        $this->record('c', 'd', '/', $today - 86400);
        $this->record('c', 'd', '/news', $today - 86400 + 60);
        $result = $this->analytics->report(gmdate('Y-m-d', $today - 86400), gmdate('Y-m-d'), compare: true);
        self::assertSame(gmdate('Y-m-d', $today - 3 * 86400), $result['comparison']['from']);
        self::assertSame(gmdate('Y-m-d', $today - 2 * 86400), $result['comparison']['to']);
        self::assertSame(['views' => 1, 'visits' => 1, 'visitors' => 1], $result['comparison']['totals']);
        self::assertSame(['views' => 100.0, 'visits' => 0.0, 'visitors' => 0.0, 'depth' => 100.0], $result['comparison']['changes']);
        $zero = $this->analytics->report(gmdate('Y-m-d', $today - 3 * 86400), gmdate('Y-m-d', $today - 3 * 86400), compare: true);
        self::assertNull($zero['comparison']['changes']['views']);
        $empty = $this->analytics->report(gmdate('Y-m-d', $today - 8 * 86400), gmdate('Y-m-d', $today - 8 * 86400), compare: true);
        self::assertSame(0.0, $empty['comparison']['changes']['views']);
        $expired = $this->analytics->report(gmdate('Y-m-d', $today - 89 * 86400), gmdate('Y-m-d'), compare: true);
        self::assertFalse($expired['comparison']['available']);
        self::assertArrayNotHasKey('totals', $expired['comparison']);
    }

    public function testFiltersApplyTogetherToCurrentComparisonAndHourlyReports(): void
    {
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        foreach ([$today - 86400, $today] as $day) {
            $visit = $day === $today ? 'b' : 'c';
            $this->record('a', $visit, '/news/item', $day + 3660, 'search.example', 'mobile');
            $this->record('a', $visit, '/newsletter', $day + 3720, 'search.example', 'mobile');
        }
        $this->record('d', 'e', '/news/item', $today + 3660, 'search.example');
        $this->record('f', 'a', '/news/item', $today + 3660, '', 'mobile');
        $result = $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), 'search.example', 'mobile', '/news', group: 'hour', compare: true);
        self::assertSame(['views' => 1, 'visits' => 1, 'visitors' => 1], $result['totals']);
        self::assertSame($result['totals'], $result['comparison']['totals']);
        self::assertSame(1, $result['hourly'][1]['views']);
        self::assertSame(1, array_sum(array_column($result['hourly'], 'views')));
        self::assertSame('/news/item', $result['pages'][0]['path']);
        self::assertSame(['mobile'], array_column($result['devices'], 'device'));
        self::assertSame(['search.example'], array_column($result['sources'], 'source'));
        self::assertSame(1, $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), '@direct')['totals']['views']);
        $this->record('b', 'c', '/news_%/item', $today + 7200);
        self::assertSame(1, $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), prefix: '/news_%')['totals']['views']);
        self::assertSame(0, $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), prefix: '/news', path: '/newsletter')['totals']['views']);
    }

    public function testHourlyBucketsUseUtcAndDeduplicateAcrossDays(): void
    {
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        $this->record('a', 'b', '/', $today - 86400);
        $this->record('a', 'c', '/', $today + 3599);
        $this->record('a', 'c', '/', $today + 3600);
        $this->record('d', 'e', '/', $today + 60);
        $result = $this->analytics->report(gmdate('Y-m-d', $today - 86400), gmdate('Y-m-d'));
        self::assertCount(24, $result['hourly']);
        self::assertSame(['hour' => '00:00', 'views' => 3, 'visits' => 3, 'visitors' => 2], $result['hourly'][0]);
        self::assertSame(1, $result['hourly'][1]['views']);
        self::assertSame(0, $result['hourly'][23]['views']);
        self::assertSame(3, $result['totals']['visits']);
    }

    public function testPageDetailsFindVisitBoundariesBeforeApplyingDateAndPathFilters(): void
    {
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        $this->record('a', 'b', '/', $today - 60);
        $this->record('a', 'b', '/news/story', $today);
        $this->record('a', 'b', '/finish', $today + 60);
        $this->record('c', 'd', '/news/story', $today + 60);
        $this->record('c', 'd', '/finish', $today + 60);
        $this->record('e', 'f', '/', $today + 60);
        $this->record('e', 'f', '/news/story', $today + 120);
        $this->record('e', 'f', '/news/story', $today + 180);
        $result = $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), path: '/news/story');
        self::assertSame(['views' => 4, 'visits' => 3, 'visitors' => 3], $result['totals']);
        self::assertSame(1, $result['page']['entrances']);
        self::assertSame(1, $result['page']['exits']);
        self::assertSame('/news/story', $result['page']['path']);
        self::assertNull($this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'))['page']);
    }

    public function testPageTitlesUseOnlyPublicNavigationProvidersAndFallBackToPaths(): void
    {
        $navigation = new \MCMS\Core\Navigation\NavigableResourceRegistry();
        $provider = $this->createMock(\MCMS\Core\Navigation\PublicResourceProviderInterface::class);
        $provider->method('id')->willReturn('example.page');
        $provider->expects(self::never())->method('resources');
        $provider->method('resolveUrl')->willReturnCallback(static fn (string $path): ?array => $path === '/public'
            ? ['id' => '1', 'label' => 'Public title', 'url' => '/public'] : null);
        $navigation->register($provider);
        $analytics = new Analytics($this->db, $navigation);
        $this->record('a', 'b', '/public', time());
        $this->record('a', 'b', '/removed', time());
        $result = $analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), path: '/public');
        self::assertSame('Public title', $result['page']['title']);
        self::assertSame('Public title', $result['pages'][0]['title']);
        self::assertSame('/removed', $analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), path: '/removed')['page']['title']);
    }

    public function testInvalidSegmentsAreRejected(): void
    {
        foreach ([['device' => 'watch'], ['source' => '<script>'], ['prefix' => 'https://example.com/'],
            ['path' => "/secret\0"], ['path' => '/?token=secret'], ['group' => 'minute']] as $filters) {
            try {
                $this->analytics->report(gmdate('Y-m-d'), gmdate('Y-m-d'), ...$filters);
                self::fail('Invalid segment accepted');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    private function request(string $path): IncomingRequest
    {
        $request = new IncomingRequest(config('App'), new URI('http://example.com' . $path), null, new UserAgent());
        $request->setMethod('GET');
        $request->setHeader('User-Agent', 'Mozilla/5.0 Firefox/130');
        $request->setGlobal('cookie', ['mcms_analytics_consent' => 'yes']);
        return $request;
    }

    private function response(): Response
    {
        return (new Response(config('App')))->setStatusCode(200)->setContentType('text/html')->setBody('<html><p>Page</p></html>');
    }
}
