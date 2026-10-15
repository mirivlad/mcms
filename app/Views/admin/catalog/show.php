<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="mb-3"><a href="<?= site_url('admin/extensions?type=' . $entry['type']) ?>"><?= esc(lang('Catalog.title')) ?></a></div>
<div class="card mb-4"><div class="card-body">
<h2 class="card-title"><?= esc($entry['name']) ?></h2><p><?= esc($entry['description']) ?></p>
<p><?= esc($entry['author']) ?> · <?= esc($entry['license']) ?> · <a href="<?= esc($entry['project'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Catalog.source')) ?></a></p>
<p><?= esc(implode(', ', $entry['tags'])) ?></p>
<p><span class="badge bg-secondary-lt"><?= esc(lang('Catalog.state_' . $entry['state'])) ?></span> <?= esc(lang('Catalog.current')) ?>: <?= esc($entry['installed'] ?? '—') ?> · <?= esc(lang('Catalog.available')) ?>: <?= esc($entry['available']['version'] ?? $entry['version']) ?></p>
<?php if (! $entry['compatible']): ?><div class="alert alert-warning"><?= esc(lang('Catalog.incompatible')) ?>: <?= esc($entry['reason']) ?></div><?php endif ?>
<?php if ($entry['type'] === 'module' && $entry['enabled'] && $entry['update']): ?><div class="alert alert-info"><?= esc(lang('Catalog.disableFirst')) ?></div><?php endif ?>
<div class="d-flex gap-2 flex-wrap">
<?php if ($entry['available'] !== null && ($entry['installed'] === null || $entry['update'])): ?>
<form method="post" action="<?= site_url('admin/extensions/' . rawurlencode($entry['id']) . '/prepare') ?>"><?= csrf_field() ?><input type="hidden" name="version" value="<?= esc($entry['available']['version'], 'attr') ?>"><button class="btn btn-primary" <?= $entry['type'] === 'module' && $entry['enabled'] ? 'disabled' : '' ?>><?= esc(lang('Catalog.' . ($entry['installed'] !== null ? 'update' : ($entry['retained'] ? 'restore' : 'install')))) ?></button></form>
<?php endif ?>
<?php if ($entry['installed'] !== null): ?>
    <?php if ($entry['type'] === 'module'): ?>
        <form method="post" action="<?= site_url('admin/modules/' . rawurlencode($entry['id']) . '/' . ($entry['enabled'] ? 'disable' : 'enable')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary"><?= esc(lang('MCMS.common.' . ($entry['enabled'] ? 'disable' : 'enable'))) ?></button></form>
    <?php else: ?>
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/themes/' . rawurlencode($entry['id'])) ?>"><?= esc(lang('MCMS.themes.configure')) ?></a>
    <?php endif ?>
    <a class="btn btn-outline-danger" href="<?= site_url('admin/extensions/' . $entry['type'] . '/' . rawurlencode($entry['id']) . '/remove') ?>"><?= esc(lang('Extensions.remove')) ?></a>
<?php endif ?>
</div>
<p class="text-secondary small mt-3 mb-0"><?= esc(lang('Catalog.reviewHint')) ?></p>
</div></div>
<div class="row row-cards mb-4"><?php foreach ($entry['screenshots'] as $url): ?><div class="col-md-6"><a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener noreferrer"><img class="img-fluid rounded" src="<?= esc($url, 'attr') ?>" alt="<?= esc($entry['name'], 'attr') ?>"></a></div><?php endforeach ?></div>
<h3><?= esc(lang('Catalog.versions')) ?></h3>
<?php foreach (array_reverse($entry['versions']) as $release): ?>
<div class="card mb-3"><div class="card-body text-break">
    <h4><?= esc($release['version']) ?> · mCMS <?= esc($release['mcms']) ?></h4>
    <p><?= esc(lang('MCMS.modules.dependencies')) ?>: <?= esc(implode(', ', array_map(static fn ($dep) => is_array($dep) ? $dep['id'] . ' ' . ($dep['version'] ?? '*') : $dep, $release['requires'])) ?: '—') ?></p>
    <p style="white-space:pre-wrap"><?= esc($release['changelog']) ?></p>
    <p class="small"><?= esc(number_format($release['bytes'])) ?> bytes · SHA-256 <code><?= esc($release['sha256']) ?></code></p>
    <form method="post" action="<?= site_url('admin/extensions/' . rawurlencode($entry['id']) . '/prepare') ?>"><?= csrf_field() ?><input type="hidden" name="version" value="<?= esc($release['version'], 'attr') ?>"><button class="btn btn-sm btn-outline-secondary"><?= esc(lang('Catalog.previewVersion')) ?></button></form>
</div></div>
<?php endforeach ?>
<?= $this->endSection() ?>
