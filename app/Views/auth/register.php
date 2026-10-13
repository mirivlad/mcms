<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.register')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<h1 class="h3 mb-4"><?= esc(lang('MCMS.account.register')) ?></h1>
<?php if ($captchaUnavailable ?? false): ?>
    <p class="alert alert-warning" role="alert"><?= esc(lang('MCMS.account.captchaUnavailable')) ?></p>
<?php else: ?>
<form method="post" action="<?= site_url('register') ?>">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="register-email"><?= esc(lang('MCMS.account.email')) ?></label>
        <input class="form-control" type="email" name="email" id="register-email" autocomplete="email" maxlength="254" value="<?= esc((string) old('email'), 'attr') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="register-username"><?= esc(lang('MCMS.account.username')) ?></label>
        <input class="form-control" name="username" id="register-username" autocomplete="username" minlength="3" maxlength="30" value="<?= esc((string) old('username'), 'attr') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="register-password"><?= esc(lang('MCMS.account.password')) ?></label>
        <input class="form-control" type="password" name="password" id="register-password" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="register-password-confirm"><?= esc(lang('MCMS.account.confirmPassword')) ?></label>
        <input class="form-control" type="password" name="password_confirm" id="register-password-confirm" autocomplete="new-password" required>
    </div>
    <?= view('MCMS\Modules\Captcha\Views\widget', ['challenge' => $challenge]) ?>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.account.register')) ?></button>
</form>
<?php endif ?>
<?= $this->endSection() ?>
