<ul class="mcms-shop-cat-list">
    <?php foreach ($nodes as $node): ?>
    <?php
    $id = preg_replace('/[^a-z0-9_-]/', '', (string) $prefix) . '-node-' . (int) $node['id'];
    $children = $node['children'] ?? [];
    $active = $selectedSlug === ($node['slug'] ?? '');
    ?>
    <li class="mcms-shop-cat-item">
        <div class="mcms-shop-cat-head">
            <a href="<?= esc(site_url(ltrim((string) $node['url'], '/')), 'attr') ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                <?= view('MCMS\Modules\Catalogue\Views\category_icon', ['category' => $node]) ?>
                <span><?= esc($node['label']) ?></span>
            </a>
            <?php if ($style !== 'tree' && $children !== []): ?>
            <button class="mcms-shop-cat-expand" type="button" data-mcms-cat-toggle
                    aria-controls="<?= esc($id, 'attr') ?>" aria-expanded="false"
                    aria-label="<?= esc(lang('CatalogueSettings.expandCategories'), 'attr') ?>">⌄</button>
            <?php endif ?>
        </div>
        <?php if ($children !== []): ?>
        <div class="mcms-shop-cat-children" id="<?= esc($id, 'attr') ?>"
             <?= $style === 'tree' ? '' : 'hidden' ?>>
            <?= view('MCMS\Modules\Catalogue\Views\category_menu_nodes', [
                'nodes' => $children, 'style' => $style,
                'selectedSlug' => $selectedSlug, 'prefix' => $prefix,
            ]) ?>
        </div>
        <?php endif ?>
    </li>
    <?php endforeach ?>
</ul>
