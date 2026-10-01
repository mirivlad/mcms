<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= esc($title ?? 'mCMS') ?></title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/tabler/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= site_url('assets/vendor/tabler-icons/tabler-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= site_url('assets/css/admin.css') ?>">
</head>
<body>
<?php $currentPath = trim(service('request')->getUri()->getPath(), '/'); ?>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#admin-sidebar-menu"
                aria-controls="admin-sidebar-menu"
                aria-expanded="false"
                aria-label="Menu"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="<?= site_url('/admin') ?>">mCMS</a>
            </h1>
            <div class="collapse navbar-collapse admin-sidebar-collapse" id="admin-sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    <?php foreach ($adminMenu ?? [] as $item): ?>
                        <?php
                            $itemPath = trim((string) $item['url'], '/');
                            $isActive = $itemPath === 'admin'
                                ? $currentPath === 'admin'
                                : ($currentPath === $itemPath || str_starts_with($currentPath, $itemPath . '/'));
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= site_url(ltrim($item['url'], '/')) ?>">
                                <?php if (($item['icon'] ?? '') !== ''): ?>
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="<?= esc($item['icon'], 'attr') ?>"></i>
                                    </span>
                                <?php endif ?>
                                <span class="nav-link-title"><?= esc($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
                <div class="admin-account">
                    <div class="text-white-50 small text-truncate mb-2">
                        <?= esc($user->username ?? $user->email ?? lang('MCMS.common.user')) ?>
                    </div>
                    <a class="btn btn-outline-light btn-sm w-100" href="<?= site_url('logout') ?>">
                        <i class="ti ti-logout me-1"></i><?= esc(lang('MCMS.common.signOut')) ?>
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
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
<script src="<?= site_url('assets/vendor/tabler/tabler.min.js') ?>"></script>
</body>
</html>