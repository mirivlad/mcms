<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title"><?= esc(lang('Catalog.title')) ?></h2>
    <div class="text-secondary"><?= esc(lang('Catalog.lastCheck')) ?>: <?= esc(isset($catalogCache['checked_at']) ? date('Y-m-d H:i:s T', $catalogCache['checked_at']) : lang('Catalog.never')) ?></div></div>
    <div class="col-auto"><form method="post" action="<?= site_url('admin/extensions/refresh') ?>"><?= csrf_field() ?><input type="hidden" name="type" value="<?= esc($type, 'attr') ?>"><button class="btn btn-outline-primary"><?= esc(lang('Catalog.check')) ?></button></form></div>
</div></div>
<div class="alert alert-warning d-none" data-catalog-status></div>
<?php if (($catalogStatus['error'] ?? $catalogCache['error'] ?? null) !== null): ?><div class="alert alert-warning"><?= esc($catalogStatus['error'] ?? $catalogCache['error']) ?></div><?php endif ?>
<ul class="nav nav-tabs mb-3">
<?php foreach (['theme', 'module'] as $tab): ?>
<?php if (auth()->user()->can($tab === 'module' ? 'core.modules' : 'core.themes')): ?>
<li class="nav-item"><a class="nav-link <?= $tab === $type ? 'active' : '' ?>" href="<?= site_url('admin/extensions?type=' . $tab) ?>"><?= esc(lang('Catalog.' . $tab . 's')) ?></a></li>
<?php endif ?>
<?php endforeach ?>
</ul>
<form class="row g-2 mb-4" method="get">
    <input type="hidden" name="type" value="<?= esc($type, 'attr') ?>">
    <div class="col-md-5"><label for="catalog-query" class="form-label"><?= esc(lang('Catalog.search')) ?></label><input class="form-control" id="catalog-query" name="q" value="<?= esc($query, 'attr') ?>"></div>
    <div class="col-sm-4 col-md-3"><label for="catalog-tag" class="form-label"><?= esc(lang('Catalog.category')) ?></label><select class="form-select" id="catalog-tag" name="tag"><option value=""><?= esc(lang('Catalog.all')) ?></option><?php foreach ($tags as $option): ?><option <?= $tag === $option ? 'selected' : '' ?> value="<?= esc($option, 'attr') ?>"><?= esc($option) ?></option><?php endforeach ?></select></div>
    <div class="col-sm-4 col-md-3"><label for="catalog-state" class="form-label"><?= esc(lang('MCMS.common.status')) ?></label><select class="form-select" id="catalog-state" name="state"><option value=""><?= esc(lang('Catalog.all')) ?></option><?php foreach (['available', 'installed', 'retained', 'update'] as $option): ?><option <?= $state === $option ? 'selected' : '' ?> value="<?= esc($option, 'attr') ?>"><?= esc(lang('Catalog.state_' . $option)) ?></option><?php endforeach ?></select></div>
    <div class="col-sm-4 col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit" aria-label="<?= esc(lang('Catalog.search'), 'attr') ?>"><i class="ti ti-search" aria-hidden="true"></i></button></div>
</form>
<div class="row row-cards">
<?php foreach ($entries as $entry): ?>
    <div class="col-md-6 col-xl-4"><div class="card h-100">
    <?php if ($entry['screenshots'] !== []): ?><img class="card-img-top" src="<?= esc($entry['screenshots'][0], 'attr') ?>" alt="<?= esc($entry['name'], 'attr') ?>" loading="lazy" style="aspect-ratio:16/10;object-fit:cover"><?php endif ?>
    <div class="card-body d-flex flex-column"><h3 class="card-title"><a href="<?= site_url('admin/extensions/' . rawurlencode($entry['id'])) ?>"><?= esc($entry['name']) ?></a></h3>
    <p class="text-secondary"><?= esc($entry['description']) ?></p>
    <div class="mb-2"><span class="badge <?= $entry['update'] ? 'bg-warning-lt' : 'bg-secondary-lt' ?>"><?= esc(lang('Catalog.state_' . $entry['state'])) ?></span></div>
    <div class="small mb-3"><?= esc(lang('Catalog.current')) ?>: <?= esc($entry['installed'] ?? '—') ?> · <?= esc(lang('Catalog.available')) ?>: <?= esc($entry['available']['version'] ?? $entry['version']) ?></div>
    <a class="btn btn-outline-primary mt-auto" href="<?= site_url('admin/extensions/' . rawurlencode($entry['id'])) ?>"><?= esc(lang('Catalog.details')) ?></a>
    </div></div></div>
<?php endforeach ?>
<?php if ($entries === []): ?><div class="col-12"><div class="card"><div class="card-body"><?= esc(lang('Catalog.empty')) ?></div></div></div><?php endif ?>
</div>
<?= $this->endSection() ?>
