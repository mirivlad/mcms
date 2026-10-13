<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($this->renderSection('title')) ?> · <?= esc((string) setting('Core.siteName')) ?></title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <style>
    body { background: #f5f7fb; color: #222; }
    .mcms-account-shell { max-width: 760px; margin: 2rem auto; padding: 0 1rem; }
    .mcms-account-card { background: #fff; border: 1px solid #e3e7ee; border-radius: .75rem; padding: clamp(1.1rem, 4vw, 2rem); }
    .mcms-account-head { display: flex; gap: 1rem; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .mcms-account-head a { text-decoration: none; }
    </style>
</head>
<body>
<main class="mcms-account-shell">
    <header class="mcms-account-head">
        <a href="<?= site_url('/') ?>" class="fs-5 fw-bold"><?= esc((string) setting('Core.siteName')) ?></a>
        <nav aria-label="<?= esc(lang('MCMS.account.navigation'), 'attr') ?>">
            <?php if (auth()->loggedIn()): ?>
                <a href="<?= site_url('account') ?>" class="me-3"><?= esc(lang('MCMS.account.title')) ?></a>
                <a href="<?= site_url('account/orders') ?>" class="me-3"><?= esc(lang('MCMS.account.orders')) ?></a>
            <?php else: ?>
                <a href="<?= site_url('login') ?>" class="me-3"><?= esc(lang('MCMS.account.login')) ?></a>
                <?php if ((bool) setting('Auth.allowRegistration')): ?><a href="<?= site_url('register') ?>"><?= esc(lang('MCMS.account.register')) ?></a><?php endif ?>
            <?php endif ?>
        </nav>
    </header>
    <div class="mcms-account-card">
        <?php foreach (['message' => 'success', 'error' => 'danger'] as $key => $color): ?>
            <?php if (session()->getFlashdata($key)): ?>
                <div class="alert alert-<?= $color ?>" role="status"><?= esc((string) session()->getFlashdata($key)) ?></div>
            <?php endif ?>
        <?php endforeach ?>
        <?php if ($errors = session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ((array) $errors as $error): ?><p class="mb-1"><?= esc((string) $error) ?></p><?php endforeach ?>
            </div>
        <?php endif ?>
        <?= $this->renderSection('main') ?>
    </div>
</main>
</body>
</html>
