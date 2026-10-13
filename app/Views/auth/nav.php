<nav class="mcms-account-access" aria-label="<?= esc(lang('MCMS.account.navigation'), 'attr') ?>">
    <?php if (auth()->loggedIn()): ?>
        <a href="<?= site_url('account') ?>"><?= esc(lang('MCMS.account.title')) ?></a>
        <form action="<?= site_url('logout') ?>" method="post" class="mcms-account-logout-form">
            <?= csrf_field() ?>
            <button type="submit"><?= esc(lang('MCMS.account.logout')) ?></button>
        </form>
    <?php else: ?>
        <a href="<?= site_url('login') ?>"><?= esc(lang('MCMS.account.login')) ?></a>
        <?php if ((bool) setting('Auth.allowRegistration')): ?>
            <a href="<?= site_url('register') ?>"><?= esc(lang('MCMS.account.register')) ?></a>
        <?php endif ?>
    <?php endif ?>
</nav>
