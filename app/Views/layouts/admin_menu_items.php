<?php foreach ($items as $item): ?>
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
<?php endforeach ?>
