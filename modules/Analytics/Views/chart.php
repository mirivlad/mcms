<?php
$hourly = $filters['group'] === 'hour';
$key = $hourly ? 'hourly' : 'daily';
$labelKey = $hourly ? 'hour' : 'day';
$series = array_map(static fn (array $row): array => ['label' => $row[$labelKey], ...array_intersect_key($row, $report['totals'])], $report[$key]);
$comparison = $report['comparison'];
$previous = ($comparison['available'] ?? false)
    ? array_map(static fn (array $row): array => ['label' => $row[$labelKey], ...array_intersect_key($row, $report['totals'])], $comparison[$key]) : [];
$colours = ['views' => '#206bc4', 'visits' => '#9c36b5', 'visitors' => '#0ca678'];
$ceiling = (int) (ceil(max(4, ...array_column($series, 'views'), ...array_column($previous, 'views')) / 4) * 4);
$count = count($series);
$x = static fn (int $i): float => $count === 1 ? 500 : 8 + $i * 984 / ($count - 1);
$y = static fn (int $value): float => 232 - $value * 224 / $ceiling;
$sets = ['current' => $series];
if ($previous !== []) $sets['previous'] = $previous;
?>
<style>
.analytics-chart { display: flex; gap: .75rem; }
.analytics-scale { display: flex; flex-direction: column; justify-content: space-between; text-align: right; min-width: 2rem; height: 240px; }
.analytics-plot { flex: 1; min-width: 0; }
.analytics-plot svg { display: block; width: 100%; height: 240px; overflow: visible; }
.analytics-plot .analytics-day:focus { outline: none; }
.analytics-plot .analytics-day:hover rect, .analytics-plot .analytics-day:focus rect { fill: var(--tblr-primary); opacity: .1; }
.analytics-dot { display: inline-block; width: .65rem; height: .65rem; border-radius: 50%; margin-right: .4rem; }
[data-analytics-toggle][aria-pressed="false"] { opacity: .45; text-decoration: line-through; }
.analytics-line { display: inline-block; width: 1.5rem; border-top: 2px solid currentColor; margin-right: .4rem; vertical-align: middle; }
.analytics-line-previous { border-top-style: dashed; opacity: .65; }
</style>
<div class="card mb-4" id="analytics-dynamics" data-group="<?= esc($filters['group'], 'attr') ?>">
    <div class="card-header flex-wrap gap-3">
        <h2 class="card-title me-auto"><?= esc(lang('Analytics.' . ($hourly ? 'hourly' : 'dynamics'))) ?></h2>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($colours as $metric => $colour): ?>
            <button class="btn btn-sm btn-ghost-secondary px-2" type="button" data-analytics-toggle="<?= $metric ?>" aria-pressed="true"><span class="analytics-dot" style="background: <?= $colour ?>"></span><?= esc(lang('Analytics.' . $metric)) ?></button>
            <?php endforeach ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($hourly): ?><p class="text-secondary small"><?= esc(lang('Analytics.hourHint')) ?></p><?php endif ?>
        <?php if ($previous !== []): ?>
        <div class="d-flex gap-3 flex-wrap text-secondary small mb-3">
            <span><span class="analytics-line"></span><?= esc(lang('Analytics.current')) ?></span>
            <span><span class="analytics-line analytics-line-previous"></span><?= esc(lang('Analytics.previous')) ?></span>
        </div>
        <?php endif ?>
        <div class="analytics-chart">
            <div class="analytics-scale text-secondary small" aria-hidden="true">
                <?php foreach ([4, 3, 2, 1, 0] as $tick): ?><span><?= $ceiling * $tick / 4 ?></span><?php endforeach ?>
            </div>
            <div class="analytics-plot">
                <svg viewBox="0 0 1000 240" preserveAspectRatio="none" role="img" aria-labelledby="analytics-chart-title">
                    <title id="analytics-chart-title"><?= esc(lang('Analytics.' . ($hourly ? 'hourly' : 'dynamics'))) ?></title>
                    <?php foreach ([0, 1, 2, 3, 4] as $tick): ?>
                    <line x1="0" x2="1000" y1="<?= $y((int) ($ceiling * $tick / 4)) ?>" y2="<?= $y((int) ($ceiling * $tick / 4)) ?>" stroke="currentColor" opacity=".1" vector-effect="non-scaling-stroke" />
                    <?php endforeach ?>
                    <?php foreach (array_reverse($sets, true) as $period => $rows): ?>
                    <?php foreach ($colours as $metric => $colour): ?>
                    <polyline data-series="<?= $metric ?>" data-period="<?= $period ?>" fill="none" stroke="<?= $colour ?>" stroke-width="<?= $period === 'current' && $metric === 'visits' ? '4.5' : '2.5' ?>" vector-effect="non-scaling-stroke" <?= $period === 'previous' ? 'stroke-dasharray="7 5" opacity=".55"' : '' ?> points="<?php foreach ($rows as $i => $row): ?><?= round($x($i), 2) ?>,<?= round($y($row[$metric]), 2) ?> <?php endforeach ?>" />
                    <?php endforeach ?>
                    <?php endforeach ?>
                    <?php foreach ($series as $i => $row): ?>
                    <?php
                    $description = lang('Analytics.current') . ': ' . lang('Analytics.point', [$row['label'], $row['views'], $row['visits'], $row['visitors']]);
                    if ($previous !== []) {
                        $before = $previous[$i];
                        $description .= '. ' . lang('Analytics.previous') . ': ' . lang('Analytics.point', [$before['label'], $before['views'], $before['visits'], $before['visitors']]);
                    }
                    ?>
                    <g class="analytics-day" tabindex="0" role="img" aria-label="<?= esc($description, 'attr') ?>">
                        <title><?= esc($description) ?></title>
                        <rect x="<?= max(0, round($x($i) - 492 / $count, 2)) ?>" y="0" width="<?= round(984 / $count, 2) ?>" height="240" fill="transparent" />
                        <?php foreach (array_reverse($sets, true) as $period => $rows): ?>
                        <?php foreach ($colours as $metric => $colour): ?>
                        <circle data-series="<?= $metric ?>" data-period="<?= $period ?>" data-index="<?= $i ?>" cx="<?= round($x($i), 2) ?>" cy="<?= round($y($rows[$i][$metric]), 2) ?>" r="<?= $metric === 'visits' ? '4.5' : '3' ?>" fill="<?= $period === 'previous' ? 'var(--tblr-bg-surface)' : $colour ?>" stroke="<?= $colour ?>" stroke-width="1" vector-effect="non-scaling-stroke" <?= $period === 'previous' ? 'opacity=".65"' : '' ?> />
                        <?php endforeach ?>
                        <?php endforeach ?>
                    </g>
                    <?php endforeach ?>
                </svg>
                <div class="d-flex justify-content-between text-secondary small mt-2" aria-hidden="true">
                    <span><?= esc($series[0]['label']) ?></span>
                    <?php if ($count > 1): ?><span><?= esc($series[$count - 1]['label']) ?></span><?php endif ?>
                </div>
            </div>
        </div>
        <div class="text-secondary small mt-3" id="analytics-day-detail" aria-live="polite"><?= esc(lang('Analytics.chartHint')) ?></div>
        <details class="mt-4">
            <summary><?= esc(lang('Analytics.' . ($hourly ? 'table' : 'daily'))) ?></summary>
            <div class="table-responsive mt-3"><table class="table table-vcenter mb-0">
                <thead><tr><th scope="col"><?= esc(lang('Analytics.' . ($hourly ? 'hour' : 'date'))) ?></th><?php if ($previous !== []): ?><th scope="col"><?= esc(lang('Analytics.compare')) ?></th><?php endif ?><?php foreach ($colours as $metric => $_): ?><th scope="col" class="text-end"><?= esc(lang('Analytics.' . $metric)) ?></th><?php endforeach ?></tr></thead>
                <tbody><?php foreach ($series as $i => $_): ?><?php foreach ($sets as $period => $rows): ?><?php $row = $rows[$i]; ?>
                    <tr <?= $period === 'previous' ? 'class="text-secondary"' : '' ?>><th scope="row"><?= esc($row['label']) ?></th><?php if ($previous !== []): ?><td><?= esc(lang('Analytics.' . $period)) ?></td><?php endif ?><?php foreach ($colours as $metric => $_): ?><td class="text-end"><?= $row[$metric] ?></td><?php endforeach ?></tr>
                <?php endforeach ?><?php endforeach ?></tbody>
            </table></div>
        </details>
    </div>
</div>
<script>
(() => {
    const chart = document.getElementById('analytics-dynamics');
    const sets = <?= json_encode($sets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const buttons = [...chart.querySelectorAll('[data-analytics-toggle]')];
    buttons.forEach(button => button.addEventListener('click', () => {
        button.setAttribute('aria-pressed', button.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
        const active = buttons.filter(item => item.getAttribute('aria-pressed') === 'true').map(item => item.dataset.analyticsToggle);
        const ceiling = Math.ceil(Math.max(4, ...Object.values(sets).flatMap(rows => rows.flatMap(row => active.map(metric => row[metric])))) / 4) * 4;
        const y = value => 232 - value * 224 / ceiling;
        chart.querySelectorAll('.analytics-scale span').forEach((tick, i) => { tick.textContent = ceiling * (4 - i) / 4; });
        chart.querySelectorAll('polyline[data-series]').forEach(line => {
            const rows = sets[line.dataset.period];
            line.style.display = active.includes(line.dataset.series) ? '' : 'none';
            line.setAttribute('points', rows.map((row, i) => `${rows.length === 1 ? 500 : 8 + i * 984 / (rows.length - 1)},${y(row[line.dataset.series])}`).join(' '));
        });
        chart.querySelectorAll('circle[data-series]').forEach(point => {
            point.style.display = active.includes(point.dataset.series) ? '' : 'none';
            point.setAttribute('cy', y(sets[point.dataset.period][point.dataset.index][point.dataset.series]));
        });
    }));
    const detail = document.getElementById('analytics-day-detail');
    chart.querySelectorAll('.analytics-day').forEach(day => {
        const show = () => { detail.textContent = day.getAttribute('aria-label'); };
        day.addEventListener('pointerenter', show);
        day.addEventListener('pointerdown', show);
        day.addEventListener('focus', show);
    });
})();
</script>
