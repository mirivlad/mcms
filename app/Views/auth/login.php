<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.login')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<h1 class="h3 mb-4"><?= esc(lang('MCMS.account.login')) ?></h1>
<form method="post" action="<?= site_url('login') ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="login-email"><?= esc(lang('MCMS.account.email')) ?></label>
        <input class="form-control" type="email" id="login-email" name="email" value="<?= esc((string) old('email'), 'attr') ?>" autocomplete="email" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label" for="login-password"><?= esc(lang('MCMS.account.password')) ?></label>
        <input class="form-control" type="password" id="login-password" name="password" autocomplete="current-password" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" value="1" id="login-remember">
        <label class="form-check-label" for="login-remember"><?= esc(lang('MCMS.account.remember')) ?></label>
    </div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.account.login')) ?></button>
</form>
<?php if ((bool) setting('Auth.allowRegistration')): ?>
    <p class="mt-3 mb-0"><?= esc(lang('MCMS.account.noAccount')) ?> <a href="<?= site_url('register') ?>"><?= esc(lang('MCMS.account.register')) ?></a></p>
<?php endif ?>
<?= $this->endSection() ?>
