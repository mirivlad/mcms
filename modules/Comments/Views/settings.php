<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-3"><h2 class="page-title"><?= esc(lang('Comments.Admin.settings')) ?></h2></div>
<?php if (session()->getFlashdata('message')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
<?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<form method="post" action="<?= site_url('admin/comments/settings') ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="comments-guests" name="allow_guests" value="1" <?= $allowGuests ? 'checked' : '' ?>>
            <label class="form-check-label" for="comments-guests"><?= esc(lang('Comments.Admin.allowGuests')) ?></label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="comments-auto" name="auto_approve_users" value="1" <?= $autoApprove ? 'checked' : '' ?>>
            <label class="form-check-label" for="comments-auto"><?= esc(lang('Comments.Admin.autoApprove')) ?></label>
        </div>
        <div class="mb-3">
            <label class="form-label" for="comments-depth"><?= esc(lang('Comments.Admin.maxDepth')) ?></label>
            <input class="form-control" type="number" min="1" max="8" id="comments-depth" name="max_depth" value="<?= (int) $maxDepth ?>" style="max-width:9rem" required>
        </div>
        <fieldset class="mb-3">
            <legend class="form-label"><?= esc(lang('Comments.Admin.resources')) ?></legend>
            <?php foreach ($choices as $id => $label): ?>
                <label class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="resources[]" value="<?= esc($id, 'attr') ?>" <?= in_array($id, $enabled, true) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= esc($label) ?> <small class="text-secondary">(<?= esc($id) ?>)</small></span>
                </label>
            <?php endforeach ?>
        </fieldset>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/comments') ?>"><?= esc(lang('Comments.Admin.title')) ?></a>
        <button class="btn btn-primary" type="submit"><?= esc(lang('Comments.Admin.save')) ?></button>
    </div>
</form>
<?= $this->endSection() ?>
