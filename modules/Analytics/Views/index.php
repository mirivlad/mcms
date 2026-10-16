<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$url = static fn (array $changes = []): string => site_url('admin/analytics') . '?' . http_build_query([...$filters, ...$changes]);
$format = static fn (string $metric, int|float $value): string => $metric === 'depth' ? number_format($value, 2) : (string) $value;
$page = $report['page'] ?? null;
$comparison = $report['comparison'] ?? null;
$hasComparison = $comparison['available'] ?? false;
$sources = array_values(array_unique([...$sources, $filters['source'] === '@direct' ? '' : $filters['source']]));
?>
<div class="mb-4">
    <?php if ($filters['path'] !== ''): ?><a class="d-inline-block mb-2" href="<?= esc($url(['path' => '']), 'attr') ?>"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Analytics.back')) ?></a><?php endif ?>
    <h1 class="page-title analytics-path"><?= esc($page['title'] ?? lang('Analytics.title')) ?></h1>
    <div class="text-secondary mt-1 analytics-path"><?= esc($page['path'] ?? lang('Analytics.subtitle')) ?></div>
</div>
<div class="d-flex gap-2 flex-wrap mb-3">
    <?php foreach ([7, 30, 90] as $days): ?>
    <?php $range = ['from' => gmdate('Y-m-d', time() - ($days - 1) * 86400), 'to' => gmdate('Y-m-d')]; ?>
    <a class="btn btn-sm <?= $range['from'] === $filters['from'] && $range['to'] === $filters['to'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc($url($range), 'attr') ?>"><?= esc(lang('Analytics.days', [$days])) ?></a>
    <?php endforeach ?>
</div>
<form class="card card-body mb-3" method="get">
    <?php if ($filters['path'] !== ''): ?><input type="hidden" name="path" value="<?= esc($filters['path'], 'attr') ?>"><?php endif ?>
    <div class="row g-3 align-items-end">
        <?php foreach (['from', 'to'] as $name): ?>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="analytics-<?= $name ?>"><?= esc(lang('Analytics.' . $name)) ?></label>
            <input class="form-control" id="analytics-<?= $name ?>" type="date" name="<?= $name ?>" value="<?= esc($filters[$name], 'attr') ?>" max="<?= gmdate('Y-m-d') ?>" required>
        </div>
        <?php endforeach ?>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="analytics-group"><?= esc(lang('Analytics.group')) ?></label>
            <select class="form-select" id="analytics-group" name="group">
                <?php foreach (['day' => 'byDay', 'hour' => 'byHour'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['group'] === $value ? 'selected' : '' ?>><?= esc(lang('Analytics.' . $label)) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label" for="analytics-compare"><?= esc(lang('Analytics.compare')) ?></label>
            <select class="form-select" id="analytics-compare" name="compare">
                <?php foreach (['1' => 'comparePrevious', '0' => 'compareOff'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['compare'] === (string) $value ? 'selected' : '' ?>><?= esc(lang('Analytics.' . $label)) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-12"><details <?= $filters['source'] !== '' || $filters['device'] !== '' || $filters['prefix'] !== '' ? 'open' : '' ?> data-analytics-filters>
            <summary><?= esc(lang('Analytics.filters')) ?></summary>
            <div class="row g-3 mt-1">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="analytics-source"><?= esc(lang('Analytics.source')) ?></label>
                    <select class="form-select" id="analytics-source" name="source">
                        <option value=""><?= esc(lang('Analytics.allSources')) ?></option>
                        <?php foreach ($sources as $source): ?>
                        <?php $value = $source === '' ? '@direct' : $source; ?>
                        <option value="<?= esc($value, 'attr') ?>" <?= $filters['source'] === $value ? 'selected' : '' ?>><?= esc($source ?: lang('Analytics.direct')) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="analytics-device"><?= esc(lang('Analytics.devices')) ?></label>
                    <select class="form-select" id="analytics-device" name="device">
                        <option value=""><?= esc(lang('Analytics.allDevices')) ?></option>
                        <?php foreach (['desktop', 'mobile', 'tablet'] as $device): ?>
                        <option value="<?= $device ?>" <?= $filters['device'] === $device ? 'selected' : '' ?>><?= esc(lang('Analytics.device.' . $device)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="analytics-prefix"><?= esc(lang('Analytics.section')) ?></label>
                    <input class="form-control" id="analytics-prefix" name="prefix" maxlength="500" value="<?= esc($filters['prefix'], 'attr') ?>" placeholder="<?= esc(lang('Analytics.sectionPlaceholder'), 'attr') ?>">
                </div>
            </div>
        </details></div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit"><?= esc(lang('Analytics.apply')) ?></button>
            <?php if ($filters['source'] !== '' || $filters['device'] !== '' || $filters['prefix'] !== ''): ?><a class="btn" href="<?= esc($url(['source' => '', 'device' => '', 'prefix' => '']), 'attr') ?>"><?= esc(lang('Analytics.reset')) ?></a><?php endif ?>
        </div>
    </div>
</form>
<p class="text-secondary small mb-4"><?= esc(lang('Analytics.periodHint')) ?></p>
<?php if ($error !== null): ?>
<div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php else: ?>
<?php
$totals = $report['totals'];
$metrics = $totals + ['depth' => $totals['visits'] === 0 ? 0.0 : $totals['views'] / $totals['visits']];
$previousMetrics = $hasComparison ? $comparison['totals'] + ['depth' => $comparison['totals']['visits'] === 0 ? 0.0 : $comparison['totals']['views'] / $comparison['totals']['visits']] : [];
?>
<?php if ($comparison !== null): ?>
<div class="mb-3 small text-secondary" data-analytics-comparison="<?= $hasComparison ? 'available' : 'unavailable' ?>">
    <?php if ($hasComparison): ?>
    <?= esc(lang('Analytics.current')) ?>: <?= esc($filters['from']) ?> — <?= esc($filters['to']) ?>.
    <?= esc(lang('Analytics.previous')) ?>: <?= esc($comparison['from']) ?> — <?= esc($comparison['to']) ?>.
    <?php else: ?><?= esc(lang('Analytics.comparisonMissing')) ?><?php endif ?>
</div>
<?php endif ?>
<div class="row row-cards mb-4">
    <?php foreach ($metrics as $metric => $value): ?>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="text-secondary mb-2"><?= esc(lang('Analytics.' . ($metric === 'depth' && $page !== null ? 'pageDepth' : $metric))) ?></div>
        <div class="h1 mb-0" data-analytics-total="<?= $metric ?>"><?= esc($format($metric, $value)) ?></div>
        <?php if ($hasComparison): ?>
        <?php $change = $comparison['changes'][$metric]; ?>
        <div class="small text-secondary mt-2" data-analytics-previous="<?= $metric ?>"><?= esc(lang('Analytics.was', [$format($metric, $previousMetrics[$metric])])) ?></div>
        <div class="small mt-1 <?= $change === null ? 'text-secondary' : ($change < 0 ? 'text-danger' : 'text-success') ?>" data-analytics-change="<?= $metric ?>"><?= $change === null ? esc(lang('Analytics.noPercent')) : esc($change == 0 ? '0%' : ($change > 0 ? '+' : '') . $change . '%') ?></div>
        <?php endif ?>
    </div></div></div>
    <?php endforeach ?>
</div>
<?php if ($page !== null): ?>
<div class="card card-body mb-4">
    <div class="row g-3">
        <?php foreach (['entrances', 'exits'] as $metric): ?>
        <div class="col-6"><span class="text-secondary" title="<?= esc(lang('Analytics.boundaryHint'), 'attr') ?>" data-bs-toggle="tooltip"><?= esc(lang('Analytics.' . $metric)) ?> <i class="ti ti-info-circle" aria-hidden="true"></i></span><div class="h2 mb-0 mt-2" data-analytics-page="<?= $metric ?>"><?= $page[$metric] ?></div></div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>
<?php if ($totals['views'] === 0 && (! $hasComparison || $comparison['totals']['views'] === 0)): ?>
<div class="card mb-4"><div class="card-body text-center py-5">
    <i class="ti ti-chart-line fs-1 text-secondary" aria-hidden="true"></i>
    <h2 class="h3 mt-3"><?= esc(lang('Analytics.emptyTitle')) ?></h2>
    <p class="text-secondary mb-0"><?= esc(lang('Analytics.empty')) ?></p>
</div></div>
<?php else: ?>
<?= view('MCMS\\Modules\\Analytics\\Views\\chart', ['report' => $report, 'filters' => $filters]) ?>
<div class="row row-cards mb-4">
    <?php if ($page === null): ?>
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.pages')) ?></h2></div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th scope="col"><?= esc(lang('Analytics.path')) ?></th><th scope="col" class="text-end"><?= esc(lang('Analytics.views')) ?></th><th scope="col" class="text-end"><?= esc(lang('Analytics.visitors')) ?></th></tr></thead>
            <tbody><?php foreach ($report['pages'] as $row): ?><tr>
                <th scope="row" class="analytics-path"><a href="<?= esc($url(['path' => $row['path']]), 'attr') ?>"><?= esc($row['title']) ?></a><?php if ($row['title'] !== $row['path']): ?><div class="text-secondary small fw-normal"><?= esc($row['path']) ?></div><?php endif ?></th>
                <td class="text-end"><?= (int) $row['views'] ?></td><td class="text-end"><?= (int) $row['visitors'] ?></td>
            </tr><?php endforeach ?><?php if ($report['pages'] === []): ?><tr><td colspan="3" class="text-secondary py-4"><?= esc(lang('Analytics.noData')) ?></td></tr><?php endif ?></tbody>
        </table></div>
    </div></div>
    <?php endif ?>
    <div class="<?= $page === null ? 'col-lg-5' : 'col-12' ?>"><div class="row row-cards">
        <div class="<?= $page === null ? 'col-12' : 'col-lg-6' ?>"><div class="card h-100">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.sources')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($report['sources'] as $row): ?>
                <div class="mb-3">
                    <div class="d-flex gap-2 justify-content-between mb-1"><span class="analytics-source"><?= esc($row['source'] ?: lang('Analytics.direct')) ?></span><span class="text-nowrap"><?= (int) $row['visits'] ?></span></div>
                    <div class="progress progress-sm" aria-hidden="true"><div class="progress-bar bg-primary" style="width: <?= round(100 * (int) $row['visits'] / $totals['visits'], 1) ?>%"></div></div>
                </div>
                <?php endforeach ?>
                <?php if ($report['sources'] === []): ?><p class="text-secondary"><?= esc(lang('Analytics.noData')) ?></p><?php endif ?>
                <div class="text-secondary small"><?= esc(lang('Analytics.visits')) ?></div>
            </div>
        </div></div>
        <div class="<?= $page === null ? 'col-12' : 'col-lg-6' ?>"><div class="card h-100">
            <div class="card-header"><h2 class="card-title"><?= esc(lang('Analytics.devices')) ?></h2></div>
            <div class="card-body">
                <?php foreach ($report['devices'] as $row): ?>
                <?php $share = round(100 * (int) $row['visits'] / $totals['visits'], 1); ?>
                <div class="mb-3">
                    <div class="d-flex gap-2 justify-content-between mb-1"><span><?= esc(lang('Analytics.device.' . $row['device'])) ?></span><span class="text-nowrap"><?= (int) $row['visits'] ?> <span class="text-secondary">(<?= $share ?>%)</span></span></div>
                    <div class="progress progress-sm" aria-hidden="true"><div class="progress-bar bg-teal" style="width: <?= $share ?>%"></div></div>
                </div>
                <?php endforeach ?>
                <?php if ($report['devices'] === []): ?><p class="text-secondary"><?= esc(lang('Analytics.noData')) ?></p><?php endif ?>
                <div class="text-secondary small"><?= esc(lang('Analytics.visits')) ?></div>
            </div>
        </div></div>
    </div></div>
</div>
<?php if ($page === null): ?><p class="text-secondary small"><?= esc(lang('Analytics.topHint')) ?></p><?php endif ?>
<?php endif ?>
<?php endif ?>
<style>
.analytics-path, .analytics-source { overflow-wrap: anywhere; white-space: normal; }
.analytics-source { min-width: 0; }
</style>
<?= $this->endSection() ?>
