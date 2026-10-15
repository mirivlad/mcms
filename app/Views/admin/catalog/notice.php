<?php
$user = auth()->user();
if ($user !== null && ($user->can('core.modules') || $user->can('core.themes')) && service('mcmsModuleRuntime')->maintenance() === null):
    $catalog = service('mcmsExtensionCatalog');
    $count = ($user->can('core.modules') ? $catalog->updateCount('module') : 0)
        + ($user->can('core.themes') ? $catalog->updateCount('theme') : 0);
?>
<div class="mb-3 <?= $count === 0 ? 'd-none' : '' ?>" data-catalog-notice><a class="badge bg-warning-lt" href="<?= site_url('admin/extensions?state=update&type=' . ($user->can('core.modules') && $catalog->updateCount('module') > 0 ? 'module' : 'theme')) ?>"><?= esc(lang('Catalog.updates')) ?>: <span data-catalog-count><?= $count ?></span></a></div>
<?php if ($catalog->due()): ?>
<div hidden data-catalog-refresh data-url="<?= site_url('admin/extensions/check') ?>"></div>
<script src="<?= base_url('assets/admin/catalog.js') ?>" defer></script>
<?php endif ?>
<?php endif ?>
