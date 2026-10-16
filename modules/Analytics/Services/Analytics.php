<?php

declare(strict_types=1);

namespace MCMS\Modules\Analytics\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Analytics
{
    public const RETENTION_DAYS = 90;
    public const VISIT_SECONDS = 1800;

    public function __construct(private readonly BaseConnection $db)
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

    public function report(string $from, string $to): array
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
        $this->prune(time());
        $counts = 'COUNT(*) AS views, COUNT(DISTINCT p.visit_id) AS visits, COUNT(DISTINCT v.visitor) AS visitors';
        $totals = $this->builder($from, $to)->select($counts, false)->get()->getRowArray();
        $totals = array_map('intval', $totals);
        $rows = $this->builder($from, $to)->select('p.day, ' . $counts, false)->groupBy('p.day')->get()->getResultArray();
        $byDay = array_column($rows, null, 'day');
        $daily = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $daily[] = ['day' => $key, ...array_map('intval', array_intersect_key($byDay[$key] ?? [], $totals))]
                + ['views' => 0, 'visits' => 0, 'visitors' => 0];
        }
        $pages = $this->builder($from, $to)->select('p.path, COUNT(*) AS views, COUNT(DISTINCT v.visitor) AS visitors', false)
            ->groupBy('p.path')->orderBy('views', 'DESC')->orderBy('p.path')->limit(20)->get()->getResultArray();
        $sources = $this->builder($from, $to)->select('v.source, COUNT(DISTINCT p.visit_id) AS visits', false)
            ->groupBy('v.source')->orderBy('visits', 'DESC')->orderBy('v.source')->limit(20)->get()->getResultArray();
        $devices = $this->builder($from, $to)->select('v.device, COUNT(DISTINCT p.visit_id) AS visits', false)
            ->groupBy('v.device')->orderBy('visits', 'DESC')->get()->getResultArray();

        return compact('totals', 'daily', 'pages', 'sources', 'devices');
    }

    private function builder(string $from, string $to): BaseBuilder
    {
        return $this->db->table('analytics_pageviews p')->join('analytics_visits v', 'v.id = p.visit_id')
            ->where('p.day >=', $from)->where('p.day <=', $to);
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
