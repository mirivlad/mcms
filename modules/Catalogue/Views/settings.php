<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('CatalogueSettings.title')) ?></h2>
<div class="card"><div class="card-body">
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="per-page"><?= esc(lang('CatalogueSettings.perPage')) ?></label>
        <input class="form-control" id="per-page" name="per_page" type="number" min="1" max="100" required value="<?= esc((string) old('per_page', $perPage), 'attr') ?>">
        <div class="form-hint"><?= esc(lang('CatalogueSettings.pageHint')) ?></div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="catalogue-menu-style"><?= esc(lang('CatalogueSettings.menuStyle')) ?></label>
        <select class="form-select" name="category_menu_style" id="catalogue-menu-style">
            <?php foreach (['tree', 'collapsed', 'horizontal'] as $style): ?>
                <option value="<?= esc($style, 'attr') ?>" <?= $categoryMenuStyle === $style ? 'selected' : '' ?>><?= esc(lang('CatalogueSettings.style.' . $style)) ?></option>
            <?php endforeach ?>
        </select>
        <div class="form-hint"><?= esc(lang('CatalogueSettings.menuStyleHint')) ?></div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="catalogue-secondary-menu"><?= esc(lang('CatalogueSettings.secondaryMenu')) ?></label>
        <select class="form-select" id="catalogue-secondary-menu" name="secondary_menu_id" <?= $menuAvailable ? '' : 'disabled' ?>>
            <option value="0"><?= esc(lang('CatalogueSettings.noSecondaryMenu')) ?></option>
            <?php foreach ($availableMenus as $menu): ?>
                <option value="<?= (int) $menu['id'] ?>" <?= $secondaryMenuId === (int) $menu['id'] ? 'selected' : '' ?>><?= esc($menu['name']) ?></option>
            <?php endforeach ?>
        </select>
        <?php if (! $menuAvailable): ?>
            <div class="form-hint"><?= esc(lang('CatalogueSettings.menusUnavailable')) ?></div>
        <?php endif ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="catalogue-menu-scope"><?= esc(lang('CatalogueSettings.secondaryScope')) ?></label>
        <select class="form-select" id="catalogue-menu-scope" name="secondary_menu_scope">
            <?php foreach (['listing', 'all_shop'] as $scope): ?>
                <option value="<?= $scope ?>" <?= $secondaryMenuScope === $scope ? 'selected' : '' ?>><?= esc(lang('CatalogueSettings.scope.' . $scope)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
</form>
</div></div>
<?= $this->endSection() ?>
