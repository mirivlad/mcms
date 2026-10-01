<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a href="<?= site_url('admin/themes') ?>" class="text-secondary text-decoration-none">
                    <?= esc(lang('MCMS.themes.title')) ?>
                </a>
            </div>
            <h2 class="page-title d-flex align-items-center gap-2">
                <?= esc($manager->displayName($theme)) ?>
                <?php if ($activeId === $theme->id): ?>
                    <span class="badge bg-primary-lt"><?= esc(lang('MCMS.themes.active')) ?></span>
                <?php endif ?>
            </h2>
            <div class="text-secondary mt-1"><?= esc(lang('MCMS.themes.configureIntro')) ?></div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-4">
        <div class="card">
            <?php if ($theme->screenshot !== null): ?>
                <img
                    src="<?= esc($manager->assetUrl($theme->screenshot, $theme), 'attr') ?>"
                    class="card-img-top"
                    alt="<?= esc($manager->displayName($theme), 'attr') ?>"
                >
            <?php endif ?>
            <div class="card-body">
                <h3 class="card-title"><?= esc($manager->displayName($theme)) ?></h3>
                <?php if ($manager->displayDescription($theme) !== ''): ?>
                    <p class="text-secondary"><?= esc($manager->displayDescription($theme)) ?></p>
                <?php endif ?>
                <div class="text-secondary small">
                    <?= esc(sprintf(lang('MCMS.themes.version'), $theme->version)) ?>
                    <?php if ($theme->author !== null): ?>
                        · <?= esc($theme->author) ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <div class="card-header">
                <div>
                    <h3 class="card-title"><?= esc(lang('MCMS.themes.menuAssignments')) ?></h3>
                    <div class="card-subtitle"><?= esc(lang('MCMS.themes.menuAssignmentsHint')) ?></div>
                </div>
            </div>

            <div class="card-body">
                <?php if (! $menusAvailable): ?>
                    <div class="alert alert-warning mb-0">
                        <?= esc(lang('MCMS.themes.menusUnavailable')) ?>
                    </div>
                <?php elseif ($theme->menuLocations === []): ?>
                    <div class="text-secondary"><?= esc(lang('MCMS.themes.noMenuLocations')) ?></div>
                <?php else: ?>
                    <?php if ($menus === []): ?>
                        <div class="alert alert-info">
                            <?= esc(lang('MCMS.themes.noMenus')) ?>
                            <a href="<?= site_url('admin/menus') ?>" class="alert-link"><?= esc(lang('MCMS.themes.openMenus')) ?></a>
                        </div>
                    <?php endif ?>

                    <div class="vstack gap-4">
                        <?php foreach ($theme->menuLocations as $locationId => $location): ?>
                            <div>
                                <input type="hidden" name="location[]" value="<?= esc($locationId, 'attr') ?>">
                                <label class="form-label" for="menu-<?= esc(md5($locationId), 'attr') ?>">
                                    <?= esc($manager->displayLocationLabel($location)) ?>
                                </label>
                                <div class="text-secondary small mb-2">
                                    <code><?= esc($locationId) ?></code>
                                    <?php if (($location['max_depth'] ?? null) !== null): ?>
                                        · <?= esc(sprintf(lang('MCMS.themes.depth'), (int) $location['max_depth'])) ?>
                                    <?php endif ?>
                                </div>
                                <?php if ($manager->displayLocationDescription($location) !== ''): ?>
                                    <div class="text-secondary mb-2">
                                        <?= esc($manager->displayLocationDescription($location)) ?>
                                    </div>
                                <?php endif ?>

                                <select
                                    class="form-select"
                                    id="menu-<?= esc(md5($locationId), 'attr') ?>"
                                    name="menu_id[]"
                                    <?= $menusAvailable ? '' : 'disabled' ?>
                                >
                                    <option value=""><?= esc(lang('MCMS.themes.noMenu')) ?></option>
                                    <?php foreach ($menus as $menu): ?>
                                        <option
                                            value="<?= (int) $menu['id'] ?>"
                                            <?= ($assignments[$locationId] ?? null) === (int) $menu['id'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($menu['name']) ?>
                                            <?php if (($menu['handle'] ?? '') !== ''): ?>
                                                (<?= esc($menu['handle']) ?>)
                                            <?php endif ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>

            <?php if ($menusAvailable && $theme->menuLocations !== []): ?>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="btn btn-link px-0" href="<?= site_url('admin/themes') ?>">
                        <?= esc(lang('MCMS.common.back')) ?>
                    </a>
                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-device-floppy me-1"></i><?= esc(lang('MCMS.common.save')) ?>
                    </button>
                </div>
            <?php endif ?>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
