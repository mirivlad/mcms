<?php
$style = in_array($style ?? 'tree', ['tree', 'collapsed', 'horizontal'], true) ? $style : 'tree';
$tree = $tree ?? [];
?>
<?php if ($tree !== []): ?>
<nav class="mcms-shop-categories mcms-shop-cat-<?= esc($style, 'attr') ?>"
     aria-label="<?= esc(lang('Catalogue.categories'), 'attr') ?>">
    <?= view('MCMS\Modules\Catalogue\Views\category_menu_nodes', [
        'nodes' => $tree, 'style' => $style,
        'selectedSlug' => (string) ($selectedSlug ?? ''), 'prefix' => $prefix ?? 'shop-categories',
    ]) ?>
</nav>
<?php endif ?>
