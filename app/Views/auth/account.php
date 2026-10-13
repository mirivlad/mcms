<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.title')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<h1 class="h3 mb-4"><?= esc(lang('MCMS.account.title')) ?></h1>
<p><?= esc(lang('MCMS.account.signedInAs')) ?> <strong><?= esc((string) $user->username) ?></strong></p>
<p><?= esc(lang('MCMS.account.email')) ?>: <?= esc((string) $user->email) ?></p>
<?php if ($user->can('core.admin')): ?><p><a href="<?= site_url('admin') ?>"><?= esc(lang('MCMS.account.administration')) ?></a></p><?php endif ?>
<form action="<?= site_url('account/profile') ?>" method="post" class="mb-4">
    <?= csrf_field() ?>
    <h2 class="h5 mb-3"><?= esc(lang('MCMS.account.profile')) ?></h2>
    <div class="mb-3"><label class="form-label" for="account-username"><?= esc(lang('MCMS.account.username')) ?></label>
        <input class="form-control" id="account-username" name="username" minlength="3" maxlength="30" required autocomplete="username" value="<?= esc((string) (old('username') ?? $user->username), 'attr') ?>">
    </div>
    <div class="mb-3"><label class="form-label" for="account-full-name"><?= esc(lang('MCMS.account.fullName')) ?></label>
        <input class="form-control" id="account-full-name" name="full_name" maxlength="190" autocomplete="name" value="<?= esc((string) (old('full_name') ?? $profile['full_name']), 'attr') ?>">
    </div>
    <div class="mb-3"><label class="form-label" for="account-phone"><?= esc(lang('MCMS.account.phone')) ?></label>
        <input class="form-control" id="account-phone" name="phone" maxlength="64" autocomplete="tel" value="<?= esc((string) (old('phone') ?? $profile['phone']), 'attr') ?>">
    </div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.account.save')) ?></button>
</form>
<hr>
<form action="<?= site_url('account/password') ?>" method="post" class="mb-4">
    <?= csrf_field() ?>
    <h2 class="h5 mb-3"><?= esc(lang('MCMS.account.changePassword')) ?></h2>
    <div class="mb-3"><label class="form-label" for="current-password"><?= esc(lang('MCMS.account.currentPassword')) ?></label>
        <input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
    </div>
    <div class="mb-3"><label class="form-label" for="new-password"><?= esc(lang('MCMS.account.newPassword')) ?></label>
        <input class="form-control" id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="mb-3"><label class="form-label" for="new-password-confirm"><?= esc(lang('MCMS.account.confirmPassword')) ?></label>
        <input class="form-control" id="new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password" required>
    </div>
    <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.account.changePassword')) ?></button>
</form>
<p class="d-flex gap-3 flex-wrap"><a href="<?= site_url('account/orders') ?>"><?= esc(lang('MCMS.account.orders')) ?></a><form method="post" action="<?= site_url('logout') ?>" class="d-inline">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-link p-0"><?= esc(lang('MCMS.account.logout')) ?></button>
</form></p>
<?= $this->endSection() ?>
