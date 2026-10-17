<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4"><div class="row align-items-center"><div class="col">
<div class="page-pretitle"><?= esc(lang('Updates.system')) ?></div>
<h2 class="page-title"><?= esc(lang('Updates.title')) ?></h2></div>
<div class="col-auto"><span class="badge bg-azure-lt">mCMS <?= esc($version) ?></span></div></div></div>
<div class="row row-cards">
<div class="col-lg-7">
<form method="post" action="<?= site_url('admin/updates') ?>" class="card">
<?= csrf_field() ?>
<div class="card-header"><h3 class="card-title"><?= esc(lang('Updates.policy')) ?></h3></div>
<div class="card-body">
<p class="text-secondary"><?= esc(lang('Updates.help')) ?></p>
<?php foreach (['core','modules','themes'] as $group): ?>
<label class="form-check form-switch mb-3">
<input type="checkbox" class="form-check-input" name="<?= esc($group) ?>" value="1" <?= $policy[$group] ? 'checked' : '' ?>>
<span class="form-check-label"><strong><?= esc(lang('Updates.' . $group)) ?></strong></span>
<span class="form-check-description"><?= esc(lang('Updates.' . $group . 'Hint')) ?></span>
</label>
<?php endforeach ?>
<div class="alert alert-warning" role="alert"><?= esc(lang('Updates.warning')) ?></div>
<h4><?= esc(lang('Updates.exceptions')) ?></h4>
<?php $installed = array_filter($entries, static fn ($item) => $item['installed'] !== null); ?>
<?php if ($installed === []): ?><p class="text-secondary"><?= esc(lang('Updates.none')) ?></p><?php endif ?>
<?php foreach ($installed as $entry): ?>
<label class="form-check mb-2"><input type="checkbox" name="excluded[]" class="form-check-input"
value="<?= esc($entry['id']) ?>" <?= in_array($entry['id'], $policy['excluded'], true) ? 'checked' : '' ?>>
<span class="form-check-label"><?= esc($entry['name']) ?> (<?= esc($entry['installed']) ?>)</span></label>
<?php endforeach ?>
</div>
<div class="card-footer"><button class="btn btn-primary" type="submit"><?= esc(lang('Updates.save')) ?></button></div>
</form></div>
<div class="col-lg-5">
<?php if (is_array($corePending) && ($corePending['status'] ?? '') === 'requested'): ?>
<div class="alert alert-warning">
    <p>Ожидается проверка обновления ядра <?= esc($corePending['target'] ?? '') ?>.
       Резервная копия: <?= esc($corePending['backup'] ?? '') ?>.
       Если обновление не завершилось, проверьте Portainer перед повторным запуском.</p>
    <form method="post" action="<?= site_url('admin/updates/core/reset') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-warning" type="submit">Сбросить ожидание после проверки</button>
    </form>
</div>
<?php endif ?>
<div class="card mb-3"><div class="card-header"><h3 class="card-title"><?= esc(lang('Updates.check')) ?></h3></div>
<div class="card-body">
<p class="text-secondary"><?= esc(lang('Updates.lastCatalog')) ?>:
<?= ! empty($catalogCache['checked_at']) ? esc(gmdate('Y-m-d H:i', (int) $catalogCache['checked_at'])) . ' UTC' : esc(lang('Updates.never')) ?></p>
<?php if (! empty($catalogStatus['error'])): ?><p class="text-danger"><?= esc($catalogStatus['error']) ?></p><?php endif ?>
<p><?= esc(lang('Updates.pending')) ?>: <strong><?= count(array_filter($entries, static fn ($item) => $item['update'])) ?></strong></p>
<form action="<?= site_url('admin/updates/check') ?>" method="post"><?= csrf_field() ?>
<button type="submit" class="btn btn-outline-primary"><?= esc(lang('Updates.checkNow')) ?></button></form>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title"><?= esc(lang('Updates.history')) ?></h3></div>
<div class="card-body">
<p class="text-secondary"><?= esc(lang('Updates.lastRun')) ?>: <?= esc($status['last_run'] ?? lang('Updates.never')) ?></p>
<?php if (empty($status['events'])): ?><p><?= esc(lang('Updates.noEvents')) ?></p><?php endif ?>
<?php foreach (array_slice($status['events'] ?? [], 0, 20) as $event): ?>
<div class="border-bottom py-2"><span class="text-secondary"><?= esc($event['at'] ?? '') ?></span>
<strong><?= esc($event['type'] ?? '') ?> <?= esc($event['id'] ?? '') ?></strong> <?= esc($event['version'] ?? '') ?>
<div><?= esc($event['result'] ?? '') ?> <?= esc($event['reason'] ?? '') ?></div></div>
<?php endforeach ?></div></div></div></div>
<?= $this->endSection() ?>
