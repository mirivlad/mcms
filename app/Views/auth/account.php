<?= $this->extend('auth/layout') ?>
<?= $this->section('title') ?><?= esc(lang('MCMS.account.title')) ?><?= $this->endSection() ?>
<?= $this->section('main') ?>
<?php
$displayName = trim((string) ($profile['full_name'] ?? '')) ?: (string) $user->username;
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
?>
<header class="mcms-account-hero">
    <div class="mcms-account-avatar" aria-hidden="true"><?= esc($initial) ?></div>
    <div class="mcms-account-identity">
        <p class="mcms-account-eyebrow"><?= esc(lang('MCMS.account.title')) ?></p>
        <h1 class="mcms-account-name"><?= esc($displayName) ?></h1>
        <p class="mcms-account-meta">
            <span>@<?= esc((string) $user->username) ?></span>
            <span class="mcms-account-meta-separator" aria-hidden="true">·</span>
            <span><?= esc((string) $user->email) ?></span>
        </p>
    </div>
    <?php if ($user->can('core.admin')): ?>
    <nav class="mcms-account-actions" aria-label="<?= esc(lang('MCMS.account.quickActions'), 'attr') ?>">
        <?php if ($user->can('core.admin')): ?>
            <a href="<?= site_url('admin') ?>" class="btn btn-outline-secondary"><?= esc(lang('MCMS.account.administration')) ?> <span aria-hidden="true">↗</span></a>
        <?php endif ?>
    </nav>
    <?php endif ?>
</header>

<div class="mcms-account-workspace">
    <div class="mcms-account-form-grid">
        <section class="mcms-account-section" aria-labelledby="mcms-account-profile-heading">
            <h2 id="mcms-account-profile-heading"><?= esc(lang('MCMS.account.profile')) ?></h2>
            <p class="mcms-account-section-intro"><?= esc(lang('MCMS.account.profileHint')) ?></p>
            <form action="<?= site_url('account/profile') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="account-username"><?= esc(lang('MCMS.account.username')) ?></label>
                    <input class="form-control" id="account-username" name="username" minlength="3" maxlength="30" required autocomplete="username" value="<?= esc((string) (old('username') ?? $user->username), 'attr') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="account-full-name"><?= esc(lang('MCMS.account.fullName')) ?></label>
                    <input class="form-control" id="account-full-name" name="full_name" maxlength="190" autocomplete="name" value="<?= esc((string) (old('full_name') ?? $profile['full_name']), 'attr') ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="account-phone"><?= esc(lang('MCMS.account.phone')) ?></label>
                    <input class="form-control" id="account-phone" name="phone" maxlength="64" autocomplete="tel" value="<?= esc((string) (old('phone') ?? $profile['phone']), 'attr') ?>">
                </div>
                <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.account.save')) ?></button>
            </form>
        </section>

        <section class="mcms-account-section" aria-labelledby="mcms-account-security-heading">
            <h2 id="mcms-account-security-heading"><?= esc(lang('MCMS.account.security')) ?></h2>
            <p class="mcms-account-section-intro"><?= esc(lang('MCMS.account.securityHint')) ?></p>
            <form action="<?= site_url('account/password') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="current-password"><?= esc(lang('MCMS.account.currentPassword')) ?></label>
                    <input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new-password"><?= esc(lang('MCMS.account.newPassword')) ?></label>
                    <input class="form-control" id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="new-password-confirm"><?= esc(lang('MCMS.account.confirmPassword')) ?></label>
                    <input class="form-control" id="new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password" required>
                </div>
                <button class="btn btn-outline-primary" type="submit"><?= esc(lang('MCMS.account.changePassword')) ?></button>
            </form>
        </section>
    </div>

    <?php if ($ordersEnabled): ?>
    <section class="mcms-account-orders-overview" aria-labelledby="mcms-account-orders-heading">
        <div class="mcms-account-section-header">
            <div>
                <h2 id="mcms-account-orders-heading"><?= esc(lang('MCMS.account.recentOrders')) ?></h2>
                <p class="mcms-account-section-intro"><?= esc(lang('MCMS.account.ordersHint')) ?></p>
            </div>
            <a href="<?= site_url('account/orders') ?>" class="mcms-account-all-orders"><?= esc(lang('MCMS.account.allOrders')) ?> <span aria-hidden="true">→</span></a>
        </div>
        <?php if ($recentOrders === []): ?>
            <p class="mcms-account-orders-empty"><?= esc(lang('MCMS.account.noOrders')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mcms-account-orders-table align-middle">
                    <thead><tr>
                        <th scope="col"><?= esc(lang('MCMS.account.order')) ?></th>
                        <th scope="col"><?= esc(lang('MCMS.account.date')) ?></th>
                        <th scope="col"><?= esc(lang('MCMS.account.status')) ?></th>
                        <th scope="col"><?= esc(lang('MCMS.account.total')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a href="<?= site_url('account/orders/' . (int) $order['id']) ?>">#<?= (int) $order['id'] ?></a></td>
                            <td><?= esc((string) $order['created_at']) ?></td>
                            <td><?= esc(lang('Orders.status.' . $order['status'])) ?></td>
                            <td><?= esc(number_format((int) $order['total_minor'] / 100, 2, '.', ' ') . ' ' . $order['currency']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
