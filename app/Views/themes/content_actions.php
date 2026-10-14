<?php
/**
 * Public editor shortcuts. Links are only displayed to authenticated users
 * with the matching permission; admin controllers enforce it again.
 */
$editTargets = [
    'pages.page' => ['permission' => 'pages', 'base' => '/admin/pages', 'edit' => '/admin/pages/%d/edit'],
    'news.article' => ['permission' => 'news', 'base' => '/admin/news', 'edit' => '/admin/news/%d/edit'],
    'catalogue.product' => ['permission' => 'catalogue', 'base' => '/admin/catalogue/products', 'edit' => '/admin/catalogue/%d/edit'],
    'galleries.album' => ['permission' => 'galleries', 'base' => '/admin/galleries', 'edit' => '/admin/galleries/%d/edit'],
];
$target = $editTargets[(string) ($targetType ?? '')] ?? null;
$materialId = filter_var($targetId ?? null, FILTER_VALIDATE_INT);
$viewer = auth()->loggedIn() ? auth()->user() : null;
$canEdit = $target !== null && $materialId !== false && $materialId > 0
    && $viewer !== null && $viewer->can($target['permission'] . '.edit');
$canManage = $target !== null && $viewer !== null && $viewer->can($target['permission'] . '.view');
?>
<?php if ($canEdit || $canManage): ?>
<nav class="mcms-content-actions" aria-label="<?= esc(lang('MCMS.publicActions.label'), 'attr') ?>">
    <?php if ($canEdit): ?>
        <a href="<?= esc(site_url(ltrim(sprintf($target['edit'], $materialId), '/')), 'attr') ?>"><?= esc(lang('MCMS.publicActions.edit')) ?></a>
    <?php endif ?>
    <?php if ($canManage): ?>
        <a href="<?= esc(site_url(ltrim($target['base'], '/')), 'attr') ?>"><?= esc(lang('MCMS.publicActions.manage')) ?></a>
    <?php endif ?>
</nav>
<?php endif ?>
