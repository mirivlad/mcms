<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-3"><h2 class="page-title"><?= esc(lang('Ratings.Admin.settings')) ?></h2></div>
<?php if (session()->getFlashdata('message')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<form method="post" action="<?= site_url('admin/ratings/settings') ?>" class="card">
<?= csrf_field() ?>
<div class="card-body">
<div class="form-check form-switch mb-4">
<input class="form-check-input" type="checkbox" id="rating-guests" name="allow_guests" value="1" <?= $allowGuests ? 'checked' : '' ?>>
<label class="form-check-label" for="rating-guests"><?= esc(lang('Ratings.Admin.allowGuests')) ?></label>
</div>
<fieldset>
<legend class="form-label"><?= esc(lang('Ratings.Admin.resources')) ?></legend>
<?php foreach ($choices as $id => $label): ?>
<label class="form-check mb-2">
<input class="form-check-input" type="checkbox" name="resources[]" value="<?= esc($id, 'attr') ?>" <?= in_array($id, $enabled, true) ? 'checked' : '' ?>>
<span class="form-check-label"><?= esc($label) ?> <small class="text-secondary">(<?= esc($id) ?>)</small></span>
</label>
<?php endforeach ?>
</fieldset>
</div>
<div class="card-footer d-flex justify-content-between">
<a class="btn btn-outline-secondary" href="<?= site_url('admin/ratings') ?>"><?= esc(lang('Ratings.Admin.title')) ?></a>
<button class="btn btn-primary" type="submit"><?= esc(lang('Ratings.Admin.save')) ?></button>
</div>
</form>
<?= $this->endSection() ?>
