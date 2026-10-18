<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-4">
    <div class="row align-items-center g-3">
        <div class="col"><div class="page-pretitle"><?= esc(lang('MCMS.users.title')) ?></div>
            <h2 class="page-title"><?= esc($target->username ?? $target->email ?? ('#' . $target->id)) ?></h2>
        </div>
        <div class="col-auto"><a href="<?= site_url('admin/users') ?>" class="btn btn-outline-secondary"><?= esc(lang('MCMS.users.back')) ?></a></div>
    </div>
</div>
<?php if (session()->getFlashdata('temporaryPassword')): ?>
    <div class="alert alert-warning" role="alert">
        <strong><?= esc(lang('MCMS.users.temporaryPassword')) ?></strong>
        <code class="user-select-all"><?= esc(session()->getFlashdata('temporaryPassword')) ?></code>
        <p class="mb-0"><?= esc(lang('MCMS.users.temporaryHint')) ?></p>
    </div>
<?php endif ?>
<div class="row row-cards">
    <div class="col-lg-7">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.users.edit')) ?></h3></div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="user-username"><?= esc(lang('MCMS.users.username')) ?></label>
                    <input id="user-username" class="form-control" name="username" maxlength="30" required value="<?= esc(old('username', $target->username ?? ''), 'attr') ?>"></div>
                <div class="mb-3"><label class="form-label" for="user-email"><?= esc(lang('MCMS.users.email')) ?></label>
                    <input id="user-email" class="form-control" type="email" name="email" maxlength="254" required value="<?= esc(old('email', $target->email ?? ''), 'attr') ?>"></div>
                <div class="mb-3"><label class="form-label" for="user-full-name"><?= esc(lang('MCMS.users.fullName')) ?></label>
                    <input id="user-full-name" class="form-control" name="full_name" maxlength="190" value="<?= esc(old('full_name', $profile['full_name'] ?? ''), 'attr') ?>"></div>
                <div class="mb-3"><label class="form-label" for="user-phone"><?= esc(lang('MCMS.users.phone')) ?></label>
                    <input id="user-phone" class="form-control" name="phone" maxlength="64" value="<?= esc(old('phone', $profile['phone'] ?? ''), 'attr') ?>"></div>
                <div class="text-secondary small"><?= esc(lang('MCMS.users.registered')) ?>: <?= esc($target->created_at ? (string) $target->created_at : '—') ?>
                    · <?= esc(lang('MCMS.users.lastActive')) ?>: <?= esc($target->last_active ? (string) $target->last_active : '—') ?></div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary"><?= esc(lang('MCMS.common.save')) ?></button></div>
        </form>
    </div>
    <?php if ($canSensitive): ?>
        <div class="col-lg-5" id="password">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.users.password')) ?></h3></div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('admin/users/' . $target->id . '/action') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="password">
                        <div class="mb-3"><label for="new-password" class="form-label"><?= esc(lang('MCMS.users.newPassword')) ?></label>
                            <input id="new-password" name="password" type="password" class="form-control" autocomplete="new-password" required minlength="8"></div>
                        <div class="mb-3"><label for="confirm-password" class="form-label"><?= esc(lang('MCMS.users.confirmPassword')) ?></label>
                            <input id="confirm-password" name="password_confirm" type="password" class="form-control" autocomplete="new-password" required minlength="8"></div>
                        <button type="submit" class="btn btn-outline-primary"><?= esc(lang('MCMS.users.setPassword')) ?></button>
                    </form>
                </div>
            </div>
            <div class="card mb-3"><div class="card-body">
                <h3 class="card-title"><?= esc(lang('MCMS.users.resetPassword')) ?></h3>
                <p class="text-secondary"><?= esc(lang('MCMS.users.resetHint')) ?></p>
                <form method="post" action="<?= site_url('admin/users/' . $target->id . '/action') ?>"
                    onsubmit="return confirm(<?= esc(json_encode(lang('MCMS.users.resetConfirm')), 'attr') ?>)">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-outline-warning"><?= esc(lang('MCMS.users.resetPassword')) ?></button>
                </form>
            </div></div>
            <div class="card"><div class="card-body">
                <h3 class="card-title"><?= esc(lang('MCMS.users.delete')) ?></h3>
                <p class="text-secondary"><?= esc(lang('MCMS.users.deleteHint')) ?></p>
                <form method="post" action="<?= site_url('admin/users/' . $target->id . '/action') ?>"
                    onsubmit="return confirm(<?= esc(json_encode(lang('MCMS.users.deleteConfirm')), 'attr') ?>)">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-outline-danger"><?= esc(lang('MCMS.users.delete')) ?></button>
                </form>
            </div></div>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
