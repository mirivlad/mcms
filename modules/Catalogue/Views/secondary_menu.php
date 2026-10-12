<?php if (($menu ?? []) !== []): ?>
<nav class="mcms-shop-secondary-menu mt-3 mb-3" aria-label="<?= esc(lang('CatalogueSettings.secondaryMenu'), 'attr') ?>">
    <ul class="list-unstyled d-flex gap-3 flex-wrap">
        <?php foreach ($menu as $item): ?>
            <?php if (($item['url'] ?? null) !== null): ?>
            <li>
                <a href="<?= esc(str_starts_with((string) $item['url'], '/') ? site_url(ltrim($item['url'], '/')) : $item['url'], 'attr') ?>"
                   <?= ! empty($item['open_in_new']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= esc($item['label']) ?></a>
                <?php if (($item['children'] ?? []) !== []): ?>
                    <ul class="mt-1">
                    <?php foreach ($item['children'] as $child): ?>
                        <?php if (($child['url'] ?? null) !== null): ?>
                        <li><a href="<?= esc(str_starts_with((string) $child['url'], '/') ? site_url(ltrim($child['url'], '/')) : $child['url'], 'attr') ?>" <?= ! empty($child['open_in_new']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= esc($child['label']) ?></a></li>
                        <?php endif ?>
                    <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </li>
            <?php endif ?>
        <?php endforeach ?>
    </ul>
</nav>
<?php endif ?>
