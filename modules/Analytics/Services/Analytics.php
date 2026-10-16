<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use MCMS\Core\Navigation\NavigableResourceRegistry;
use MCMS\Core\Navigation\PublicResourceProviderInterface;
use RuntimeException;
use Throwable;

final class Analytics
{
    public const RETENTION_DAYS = 90;
    public const VISIT_SECONDS = 1800;

    public function __construct(private readonly BaseConnection $db, private readonly ?NavigableResourceRegistry $navigation = null)
    {
    }

    /** Random browser tokens are hashed before storage. Returns the current visit token. */
    public function record(string $visitor, string $visit, string $path, string $source, string $device, int $now): string
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $visitor) !== 1
            || preg_match('/^[a-f0-9]{32}$/D', $visit) !== 1
            || ! str_starts_with($path, '/') || strlen($path) > 500
            || strlen($source) > 253 || ! in_array($device, ['desktop', 'mobile', 'tablet'], true)) {
            throw new InvalidArgumentException('Invalid analytics page view.');
        }
        $this->prune($now);
        $visitorId = hash('sha256', $visitor);
        $id = hash('sha256', $visitor . $visit);
        $previous = $this->db->table('analytics_visits')->where('id', $id)->get()->getRowArray();
        if ($previous !== null && (int) $previous['last_seen'] <= $now - self::VISIT_SECONDS) {
            $visit = bin2hex(random_bytes(16));
            $id = hash('sha256', $visitor . $visit);
        }

        $this->db->transBegin();
        try {
            // Ignore a concurrent first view of the same visit; its entry attribution wins.
            $this->db->table('analytics_visits')->ignore(true)->insert([
                'id' => $id, 'visitor' => $visitorId, 'source' => $source, 'device' => $device, 'last_seen' => $now,
            ]);
            $this->db->table('analytics_visits')->where('id', $id)->where('last_seen <', $now)->update(['last_seen' => $now]);
            $this->db->table('analytics_pageviews')->insert([
                'visit_id' => $id, 'viewed_at' => $now, 'day' => gmdate('Y-m-d', $now), 'path' => $path,
            ]);
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Unable to record analytics.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        return $visit;
    }

    public function report(string $from, string $to, string $source = '', string $device = '', string $prefix = '', string $path = '', string $group = 'day', bool $compare = false): array
    {
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $from) !== 1
            || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $to) !== 1) {
            throw new InvalidArgumentException(lang('Analytics.invalidPeriod'));
        }
        $zone = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);
        if ($start === false || $end === false || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to
            || $start > $end || $start->diff($end)->days >= self::RETENTION_DAYS || $to > gmdate('Y-m-d')) {
            throw new InvalidArgumentException(lang('Analytics.invalidPeriod'));
        }
        if (! in_array($device, ['', 'desktop', 'mobile', 'tablet'], true) || ! in_array($group, ['day', 'hour'], true)
            || ($source !== '' && $source !== '@direct' && (strlen($source) > 253 || preg_match('/^(?:[a-z0-9-]+\.)*[a-z0-9-]+$/D', $source) !== 1))) {
            throw new InvalidArgumentException(lang('Analytics.invalidFilter'));
        }
        foreach ([$prefix, $path] as $value) {
            if ($value !== '' && (strlen($value) > 500 || ! str_starts_with($value, '/') || preg_match('/[\x00-\x1f\x7f?#]/', $value))) {
                throw new InvalidArgumentException(lang('Analytics.invalidFilter'));
            }
        }
        $this->prune(time());
        $filters = compact('source', 'device', 'prefix', 'path');
        $report = $this->summary($from, $to, $filters);
        $report['pages'] = $this->builder($from, $to, $filters)->select('p.path, COUNT(*) AS views, COUNT(DISTINCT v.visitor) AS visitors', false)
            ->groupBy('p.path')->orderBy('views', 'DESC')->orderBy('p.path')->limit(20)->get()->getResultArray();
        foreach ($report['pages'] as &$page) {
            $page['title'] = $this->pageTitle($page['path']);
        }
        unset($page);
        $report['sources'] = $this->builder($from, $to, $filters)->select('v.source, COUNT(DISTINCT p.visit_id) AS visits', false)
            ->groupBy('v.source')->orderBy('visits', 'DESC')->orderBy('v.source')->limit(20)->get()->getResultArray();
        $report['devices'] = $this->builder($from, $to, $filters)->select('v.device, COUNT(DISTINCT p.visit_id) AS visits', false)
            ->groupBy('v.device')->orderBy('visits', 'DESC')->get()->getResultArray();
        $report['page'] = $path === '' ? null : ['path' => $path, 'title' => $this->pageTitle($path),
            'entrances' => $this->boundaryCount($from, $to, $filters, 'ASC'),
            'exits' => $this->boundaryCount($from, $to, $filters, 'DESC')];
        $report['comparison'] = null;
        if ($compare) {
            $days = (int) $start->diff($end)->days + 1;
            $previousFrom = $start->modify('-' . $days . ' days')->format('Y-m-d');
            $previousTo = $start->modify('-1 day')->format('Y-m-d');
            $available = $previousFrom >= gmdate('Y-m-d', time() - (self::RETENTION_DAYS - 1) * 86400);
            $report['comparison'] = ['from' => $previousFrom, 'to' => $previousTo, 'available' => $available];
            if ($available) {
                $previous = $this->summary($previousFrom, $previousTo, $filters);
                $changes = [];
                foreach ($this->metrics($report['totals']) as $metric => $value) {
                    $before = $this->metrics($previous['totals'])[$metric];
                    $changes[$metric] = $before == 0 ? ($value == 0 ? 0.0 : null) : round(($value - $before) / $before * 100, 1);
                }
                $report['comparison'] += $previous + ['changes' => $changes];
            }
        }
        return $report;
    }

    public function sourceChoices(): array
    {
        return array_column($this->db->table('analytics_visits')->select('source')->distinct()->orderBy('source')->limit(200)->get()->getResultArray(), 'source');
    }

    private function pageTitle(string $path): string
    {
        foreach ($this->navigation?->all() ?? [] as $provider) {
            if ($provider instanceof PublicResourceProviderInterface && ($resource = $provider->resolveUrl($path)) !== null) {
                return $resource['label'];
            }
        }
        return $path === '/' ? lang('Analytics.home') : $path;
    }

    private function metrics(array $totals): array
    {
        return $totals + ['depth' => $totals['visits'] === 0 ? 0.0 : $totals['views'] / $totals['visits']];
    }

    private function summary(string $from, string $to, array $filters): array
    {
        $counts = 'COUNT(*) AS views, COUNT(DISTINCT p.visit_id) AS visits, COUNT(DISTINCT v.visitor) AS visitors';
        $totals = $this->builder($from, $to, $filters)->select($counts, false)->get()->getRowArray();
        $totals = array_map('intval', $totals);
        $rows = $this->builder($from, $to, $filters)->select('p.day, ' . $counts, false)->groupBy('p.day')->get()->getResultArray();
        $byDay = array_column($rows, null, 'day');
        $daily = [];
        $start = new DateTimeImmutable($from, new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($to, new DateTimeZone('UTC'));
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $daily[] = ['day' => $key, ...array_map('intval', array_intersect_key($byDay[$key] ?? [], $totals))]
                + ['views' => 0, 'visits' => 0, 'visitors' => 0];
        }
        // Integer epoch arithmetic works with MariaDB and SQLite and always uses UTC.
        $rows = $this->builder($from, $to, $filters)->select('(p.viewed_at - p.viewed_at % 3600) % 86400 AS bucket, ' . $counts, false)
            ->groupBy('bucket')->get()->getResultArray();
        $byHour = array_column($rows, null, 'bucket');
        $hourly = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourly[] = ['hour' => sprintf('%02d:00', $hour), ...array_map('intval', array_intersect_key($byHour[$hour * 3600] ?? [], $totals))]
                + ['views' => 0, 'visits' => 0, 'visitors' => 0];
        }
        return compact('totals', 'daily', 'hourly');
    }

    private function boundaryCount(string $from, string $to, array $filters, string $direction): int
    {
        // Look across the whole retained visit, before applying page/period filters.
        $boundary = $this->db->table('analytics_pageviews q')->select('q.id')->where('q.visit_id = p.visit_id', null, false)
            ->orderBy('q.viewed_at', $direction)->orderBy('q.id', $direction)->limit(1)->getCompiledSelect();
        return $this->builder($from, $to, $filters)->where('p.id = (' . $boundary . ')', null, false)->countAllResults();
    }

    private function builder(string $from, string $to, array $filters): BaseBuilder
    {
        $builder = $this->db->table('analytics_pageviews p')->join('analytics_visits v', 'v.id = p.visit_id')
            ->where('p.day >=', $from)->where('p.day <=', $to);
        if ($filters['source'] !== '') {
            $builder->where('v.source', $filters['source'] === '@direct' ? '' : $filters['source']);
        }
        if ($filters['device'] !== '') {
            $builder->where('v.device', $filters['device']);
        }
        if ($filters['path'] !== '') {
            $builder->where('p.path', $filters['path']);
        }
        $prefix = rtrim($filters['prefix'], '/');
        if ($prefix !== '') {
            $builder->groupStart()->where('p.path', $prefix)->orLike('p.path', $prefix . '/', 'after')->groupEnd();
        }
        return $builder;
    }

    /** Lazy daily cleanup also runs when the report is opened after a quiet period. */
    private function prune(int $now): void
    {
        $today = gmdate('Y-m-d', $now);
        $state = $this->db->table('analytics_state')->where('id', 1)->get()->getRowArray();
        if (($state['cleaned_day'] ?? '') >= $today) {
            return;
        }
        $cutoff = strtotime($today . ' 00:00:00 UTC') - (self::RETENTION_DAYS - 1) * 86400;
        $this->db->table('analytics_pageviews')->where('viewed_at <', $cutoff)->delete();
        $this->db->table('analytics_visits')->where('last_seen <', $cutoff)->delete();
        $this->db->table('analytics_state')->where('id', 1)->update(['cleaned_day' => $today]);
    }
}
