<?php foreach ($items as $item): ?>
    <?php if (isset($item['items'])): ?>
    <?php $sectionActive = in_array(true, array_column($item['items'], 'active'), true); ?>
    <li class="nav-item admin-menu-section">
        <details id="admin-menu-administration-<?= esc($item['id'], 'attr') ?>" <?= $sectionActive ? 'open' : '' ?>>
            <summary class="nav-link <?= $sectionActive ? 'admin-group-active' : '' ?>">
                <span class="nav-link-icon"><i class="<?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                <span class="nav-link-title"><?= esc(lang(substr($item['label'], 1))) ?></span>
                <i class="ti ti-chevron-right admin-menu-chevron" aria-hidden="true"></i>
            </summary>
            <ul class="navbar-nav admin-submenu admin-section-items">
                <?= view('layouts/admin_menu_items', ['items' => $item['items']]) ?>
            </ul>
        </details>
    </li>
    <?php else: ?>
    <li class="nav-item">
        <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= site_url(ltrim($item['url'], '/')) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
            <?php if (($item['icon'] ?? '') !== ''): ?>
                <span class="nav-link-icon">
                    <i class="<?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i>
                </span>
            <?php endif ?>
            <span class="nav-link-title"><?= esc($item['label']) ?></span>
        </a>
    </li>
    <?php endif ?>
<?php endforeach ?>
