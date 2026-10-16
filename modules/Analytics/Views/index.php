<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<style>
.analytics-chart { display: flex; gap: .75rem; }
.analytics-scale { display: flex; flex-direction: column; justify-content: space-between; text-align: right; min-width: 2rem; height: 240px; }
.analytics-plot { flex: 1; min-width: 0; }
.analytics-plot svg { display: block; width: 100%; height: 240px; overflow: visible; }
.analytics-plot .analytics-day:focus { outline: none; }
.analytics-plot .analytics-day:hover rect, .analytics-plot .analytics-day:focus rect { fill: var(--tblr-primary); opacity: .1; }
.analytics-dot { display: inline-block; width: .65rem; height: .65rem; border-radius: 50%; margin-right: .4rem; }
[data-analytics-toggle][aria-pressed="false"] { opacity: .45; text-decoration: line-through; }
.analytics-path { overflow-wrap: anywhere; white-space: normal; }
.analytics-source { overflow-wrap: anywhere; min-width: 0; }
</style>
<div class="mb-4">
    <h1 class="page-title"><?= esc(lang('Analytics.title')) ?></h1>
    <div class="text-secondary mt-1"><?= esc(lang('Analytics.subtitle')) ?></div>
</div>
<div class="d-flex gap-2 flex-wrap mb-3">
    <?php foreach ([7, 30, 90] as $days): ?>
    <?php $range = ['from' => gmdate('Y-m-d', time() - ($days - 1) * 86400), 'to' => gmdate('Y-m-d')]; ?>
    <a class="btn btn-sm <?= $range === $filters ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(site_url('admin/analytics') . '?' . http_build_query($range), 'attr') ?>"><?= esc(lang('Analytics.days', [$days])) ?></a>
    <?php endforeach ?>
</div>
<form class="row g-3 align-items-end mb-3" method="get">
    <?php foreach (['from', 'to'] as $name): ?>
    <div class="col-6 col-md-4 col-xl-3">
        <label class="form-label" for="analytics-<?= $name ?>"><?= esc(lang('Analytics.' . $name)) ?></label>
        <input class="form-control" id="analytics-<?= $name ?>" type="date" name="<?= $name ?>" value="<?= esc($filters[$name], 'attr') ?>" max="<?= gmdate('Y-m-d') ?>" required>
    </div>
    <?php endforeach ?>
    <div class="col-auto"><button class="btn btn-primary" type="submit"><?= esc(lang('Analytics.apply')) ?></button></div>
</form>
<p class="text-secondary small mb-4"><?= esc(lang('Analytics.periodHint')) ?></p>
<?php if ($error !== null): ?>
<div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php else: ?>
<?php
$totals = $report['totals'];
$colours = ['views' => '#206bc4', 'visits' => '#9c36b5', 'visitors' => '#0ca678'];
$metrics = [...$totals, 'depth' => $totals['visits'] === 0 ? '0' : number_format($totals['views'] / $totals['visits'], 1)];
$max = max(4, max(array_column($report['daily'], 'views')));
$ceiling = (int) (ceil($max / 4) * 4);
$count = count($report['daily']);
$x = static fn (int $i): float => $count === 1 ? 500 : 8 + $i * 984 / ($count - 1);
$y = static fn (int $value): float => 232 - $value * 224 / $ceiling;
?>
<div class="row row-cards mb-4">
    <?php foreach ($metrics as $metric => $value): ?>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body">
        <div class="text-secondary mb-2"><?= esc(lang('Analytics.' . $metric)) ?></div>
        <div class="h1 mb-0" data-analytics-total="<?= $metric ?>"><?= esc((string) $value) ?></div>
    </div></div></div>
    <?php endforeach ?>
</div>
<?php if ($totals['views'] === 0): ?>
<div class="card mb-4"><div class="card-body text-center py-5">
    <i class="ti ti-chart-line fs-1 text-secondary" aria-hidden="true"></i>
    <h2 class="h3 mt-3"><?= esc(lang('Analytics.emptyTitle')) ?></h2>
    <p class="text-secondary mb-0"><?= esc(lang('Analytics.empty')) ?></p>
</div></div>
<?php else: ?>
<div class="card mb-4" id="analytics-dynamics">
    <div class="card-header flex-wrap gap-3">
        <h2 class="card-title me-auto"><?= esc(lang('Analytics.dynamics')) ?></h2>
        <div class="d-flex gap-3 flex-wrap">
            <?php foreach ($colours as $metric => $colour): ?>
            <button class="btn btn-sm btn-ghost-secondary px-2" type="button" data-analytics-toggle="<?= $metric ?>" aria-pressed="true"><span class="analytics-dot" style="background: <?= $colour ?>"></span><?= esc(lang('Analytics.' . $metric)) ?></button>
            <?php endforeach ?>
        </div>
    </div>
    <div class="card-body">
        <div class="analytics-chart">
            <div class="analytics-scale text-secondary small" aria-hidden="true">
                <?php foreach ([4, 3, 2, 1, 0] as $tick): ?><span><?= $ceiling * $tick / 4 ?></span><?php endforeach ?>
            </div>
            <div class="analytics-plot">
                <svg viewBox="0 0 1000 240" preserveAspectRatio="none" role="img" aria-labelledby="analytics-chart-title analytics-chart-description">
                    <title id="analytics-chart-title"><?= esc(lang('Analytics.dynamics')) ?></title>
                    <desc id="analytics-chart-description"><?= esc(lang('Analytics.daily')) ?>: <?= esc($filters['from']) ?> — <?= esc($filters['to']) ?>. <?= esc(lang('Analytics.point', ['', $totals['views'], $totals['visits'], $totals['visitors']])) ?></desc>
                    <?php foreach ([0, 1, 2, 3, 4] as $tick): ?>
                    <line x1="0" x2="1000" y1="<?= $y((int) ($ceiling * $tick / 4)) ?>" y2="<?= $y((int) ($ceiling * $tick / 4)) ?>" stroke="currentColor" opacity=".1" vector-effect="non-scaling-stroke" />
                    <?php endforeach ?>
                    <?php foreach ($colours as $metric => $colour): ?>
                    <polyline data-series="<?= $metric ?>" fill="none" stroke="<?= $colour ?>" stroke-width="<?= $metric === 'visits' ? '4.5' : '2.5' ?>" vector-effect="non-scaling-stroke" <?= $metric === 'visits' ? 'stroke-dasharray="7 4"' : '' ?> points="<?php foreach ($report['daily'] as $i => $row): ?><?= round($x($i), 2) ?>,<?= round($y($row[$metric]), 2) ?> <?php endforeach ?>" />
                    <?php endforeach ?>
                    <?php foreach ($report['daily'] as $i => $row): ?>
                    <g class="analytics-day" tabindex="0" role="img" aria-label="<?= esc(lang('Analytics.point', [$row['day'], $row['views'], $row['visits'], $row['visitors']]), 'attr') ?>">
                        <title><?= esc(lang('Analytics.point', [$row['day'], $row['views'], $row['visits'], $row['visitors']])) ?></title>
                        <rect x="<?= max(0, round($x($i) - 492 / $count, 2)) ?>" y="0" width="<?= round(984 / $count, 2) ?>" height="240" fill="transparent" />
                        <?php foreach ($colours as $metric => $colour): ?>
                        <circle data-series="<?= $metric ?>" data-index="<?= $i ?>" cx="<?= round($x($i), 2) ?>" cy="<?= round($y($row[$metric]), 2) ?>" r="<?= $metric === 'visits' ? '4.5' : '3' ?>" fill="<?= $colour ?>" vector-effect="non-scaling-stroke" />
                        <?php endforeach ?>
                    </g>
                    <?php endforeach ?>
                </svg>
                <div class="d-flex justify-content-between text-secondary small mt-2" aria-hidden="true">
                    <span><?= esc($filters['from']) ?></span>
                    <?php if ($count > 1): ?><span><?= esc($filters['to']) ?></span><?php endif ?>
                </div>
            </div>
        </div>
        <div class="text-secondary small mt-3" id="analytics-day-detail" aria-live="polite"><?= esc(lang('Analytics.chartHint')) ?></div>
        <details class="mt-4">
            <summary><?= esc(lang('Analytics.daily')) ?></summary>
            <div class="table-responsive mt-3"><table class="table table-vcenter mb-0">
                <thead><tr><th scope="col"><?= esc(lang('Analytics.date')) ?></th><?php foreach ($colours as $metric => $_): ?><th scope="col" class="text-end"><?= esc(lang('Analytics.' . $metric)) ?></th><?php endforeach ?></tr></thead>
                <tbody><?php foreach ($report['daily'] as $row): ?><tr><th scope="row"><?= esc($row['day']) ?></th><?php foreach ($colours as $metric => $_): ?><td class="text-end"><?= $row[$metric] ?></td><?php endforeach ?></tr><?php endforeach ?></tbody>
            </table></div>
        </details>
    </div>
</div>
<script>
(() => {
    const chart = document.getElementById('analytics-dynamics');
    const days = <?= json_encode($report['daily'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const buttons = [...chart.querySelectorAll('[data-analytics-toggle]')];
    buttons.forEach(button => button.addEventListener('click', () => {
        button.setAttribute('aria-pressed', button.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
        const active = buttons.filter(item => item.getAttribute('aria-pressed') === 'true').map(item => item.dataset.analyticsToggle);
        const ceiling = Math.ceil(Math.max(4, ...days.flatMap(day => active.map(metric => day[metric]))) / 4) * 4;
        const y = value => 232 - value * 224 / ceiling;
        chart.querySelectorAll('.analytics-scale span').forEach((tick, i) => { tick.textContent = ceiling * (4 - i) / 4; });
        chart.querySelectorAll('polyline[data-series]').forEach(line => {
            line.style.display = active.includes(line.dataset.series) ? '' : 'none';
            line.setAttribute('points', days.map((day, i) => `${days.length === 1 ? 500 : 8 + i * 984 / (days.length - 1)},${y(day[line.dataset.series])}`).join(' '));
        });
        chart.querySelectorAll('circle[data-series]').forEach(point => {
            point.style.display = active.includes(point.dataset.series) ? '' : 'none';
            point.setAttribute('cy', y(days[point.dataset.index][point.dataset.series]));
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
<div class="row row-cards mb-4">
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.pages')) ?></h2></div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th scope="col"><?= esc(lang('Analytics.path')) ?></th><th scope="col" class="text-end"><?= esc(lang('Analytics.views')) ?></th><th scope="col" class="text-end"><?= esc(lang('Analytics.visitors')) ?></th></tr></thead>
            <tbody><?php foreach ($report['pages'] as $row): ?><tr>
                <th scope="row" class="analytics-path"><?= esc($row['path']) ?></th>
                <td class="text-end"><?= (int) $row['views'] ?></td><td class="text-end"><?= (int) $row['visitors'] ?></td>
            </tr><?php endforeach ?></tbody>
        </table></div>
    </div></div>
    <div class="col-lg-5"><div class="card mb-4">
        <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.sources')) ?></h2></div>
        <div class="card-body">
            <?php foreach ($report['sources'] as $row): ?>
            <div class="mb-3">
                <div class="d-flex gap-2 justify-content-between mb-1"><span class="analytics-source"><?= esc($row['source'] ?: lang('Analytics.direct')) ?></span><span class="text-nowrap"><?= (int) $row['visits'] ?></span></div>
                <div class="progress progress-sm" aria-hidden="true"><div class="progress-bar bg-primary" style="width: <?= round(100 * (int) $row['visits'] / $totals['visits'], 1) ?>%"></div></div>
            </div>
            <?php endforeach ?>
            <div class="text-secondary small"><?= esc(lang('Analytics.visits')) ?></div>
        </div>
    </div><div class="card">
        <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.devices')) ?></h2></div>
        <div class="card-body">
            <?php foreach ($report['devices'] as $row): ?>
            <?php $share = round(100 * (int) $row['visits'] / $totals['visits'], 1); ?>
            <div class="mb-3">
                <div class="d-flex gap-2 justify-content-between mb-1"><span><?= esc(lang('Analytics.device.' . $row['device'])) ?></span><span class="text-nowrap"><?= (int) $row['visits'] ?> <span class="text-secondary">(<?= $share ?>%)</span></span></div>
                <div class="progress progress-sm" aria-hidden="true"><div class="progress-bar bg-teal" style="width: <?= $share ?>%"></div></div>
            </div>
            <?php endforeach ?>
            <div class="text-secondary small"><?= esc(lang('Analytics.visits')) ?></div>
        </div>
    </div></div>
</div>
<p class="text-secondary small"><?= esc(lang('Analytics.topHint')) ?></p>
<?php endif ?>
<?php endif ?>
<details class="card"><summary class="card-body py-3"><?= esc(lang('Analytics.definitionsTitle')) ?></summary><div class="card-body pt-0 text-secondary">
    <p><?= esc(lang('Analytics.definitions')) ?></p>
    <p><?= esc(lang('Analytics.privacy')) ?></p>
    <p class="mb-0"><?= esc(lang('Analytics.limits')) ?></p>
</div></details>
<?= $this->endSection() ?>
