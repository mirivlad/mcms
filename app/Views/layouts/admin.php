<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= esc($title ?? 'mCMS') ?></title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/tabler/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= site_url('assets/vendor/tabler-icons/tabler-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= site_url('assets/css/admin.css') ?>?v=<?= substr(hash_file('sha256', FCPATH . 'assets/css/admin.css'), 0, 12) ?>">
</head>
<body>
<?php
    $currentPath = trim(service('request')->getUri()->getPath(), '/');
    $dashboardPath = trim((string) parse_url(site_url('admin'), PHP_URL_PATH), '/');
    $menuItems = $adminMenu ?? [];
    foreach ($menuItems as &$item) {
        $itemPath = trim((string) parse_url(site_url(ltrim($item['url'], '/')), PHP_URL_PATH), '/');
        $item['active'] = $currentPath === $itemPath
            || ($itemPath !== $dashboardPath && str_starts_with($currentPath, $itemPath . '/'));
    }
    unset($item);
    $menuGroups = service('mcmsAdmin')->groupedMenu($menuItems);
?>
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
                aria-label="<?= esc(lang('MCMS.nav.toggleMenu'), 'attr') ?>"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="<?= site_url('/admin') ?>">mCMS</a>
            </h1>
            <div class="collapse navbar-collapse admin-sidebar-collapse" id="admin-sidebar-menu">
                <nav class="admin-navigation pt-lg-3" aria-label="<?= esc(lang('MCMS.nav.adminNavigation'), 'attr') ?>">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link admin-site-link" href="<?= site_url('/') ?>" target="_blank" rel="noopener noreferrer" title="<?= esc(lang('MCMS.nav.newTab'), 'attr') ?>">
                                <span class="nav-link-icon"><i class="ti ti-world" aria-hidden="true"></i></span>
                                <span class="nav-link-title"><?= esc(lang('MCMS.nav.openSite')) ?></span>
                                <i class="ti ti-external-link ms-auto" aria-hidden="true"></i>
                            </a>
                        </li>
                        <?php foreach ($menuGroups as $group): ?>
                            <?php if ($group['id'] === 'main'): ?>
                                <?= view('layouts/admin_menu_items', ['items' => $group['items']]) ?>
                            <?php else: ?>
                                <?php $groupActive = in_array(true, array_column($group['items'], 'active'), true); ?>
                                <li class="nav-item admin-menu-group">
                                    <details id="admin-menu-<?= esc($group['id'], 'attr') ?>" <?= $groupActive ? 'open' : '' ?>>
                                        <summary class="nav-link <?= $groupActive ? 'admin-group-active' : '' ?>">
                                            <span class="nav-link-icon"><i class="<?= esc($group['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                                            <span class="nav-link-title"><?= esc(lang(substr($group['label'], 1))) ?></span>
                                            <i class="ti ti-chevron-right admin-menu-chevron" aria-hidden="true"></i>
                                        </summary>
                                        <ul class="navbar-nav admin-submenu">
                                            <?= view('layouts/admin_menu_items', ['items' => $group['sections'] ?? $group['items']]) ?>
                                        </ul>
                                    </details>
                                </li>
                            <?php endif ?>
                        <?php endforeach ?>
                    </ul>
                </nav>
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
                <?= view('admin/catalog/notice') ?>
                <?php if (session()->getFlashdata('message')): ?>
                    <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
                <?php endif ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif ?>

                <?php if (preg_match('#^/admin/settings(?:/|$)#', '/' . trim(service('request')->getUri()->getPath(), '/')) === 1): ?>
                    <?= view('admin/settings/navigation') ?>
                <?php endif ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
</div>
<script src="<?= site_url('assets/vendor/tabler/tabler.min.js') ?>"></script>
<script>
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.admin-icon-action').forEach(element => {
            tabler.Tooltip.getInstance(element)?.hide();
        });
    }
});
document.addEventListener('click', event => {
    const action = event.target.closest('.admin-icon-action');
    if (action) tabler.Tooltip.getInstance(action)?.hide();
});
</script>
</body>
</html>
