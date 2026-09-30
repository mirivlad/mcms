<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= esc($title ?? 'mCMS') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/css/tabler.min.css">
</head>
<body>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="<?= site_url('/admin') ?>">mCMS</a>
            </h1>
            <div class="collapse navbar-collapse show">
                <ul class="navbar-nav pt-lg-3">
                    <?php foreach ($adminMenu ?? [] as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= site_url(ltrim($item['url'], '/')) ?>">
                                <span class="nav-link-title"><?= esc($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <div class="navbar-nav flex-row order-md-last ms-auto">
                    <div class="nav-item d-flex align-items-center gap-3">
                        <span><?= esc($user->username ?? $user->email ?? 'User') ?></span>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('logout') ?>">Sign out</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-body">
            <div class="container-xl">
                <?php if (session()->getFlashdata('message')): ?>
                    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
                <?php endif ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif ?>

                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.1/dist/js/tabler.min.js"></script>
</body>
</html>
