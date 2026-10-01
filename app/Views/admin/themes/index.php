<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.themes.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(lang('MCMS.themes.intro')) ?></div>
        </div>
    </div>
</div>

<?php if ($themes === []): ?>
    <div class="card">
        <div class="empty">
            <div class="empty-img"><i class="ti ti-palette" style="font-size:4rem"></i></div>
            <p class="empty-title"><?= esc(lang('MCMS.themes.none')) ?></p>
            <p class="empty-subtitle text-secondary"><?= esc(lang('MCMS.themes.noneHint')) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cards">
        <?php foreach ($themes as $theme): ?>
            <?php $isActive = $activeId === $theme->id; ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 <?= $isActive ? 'border-primary' : '' ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-3">
                            <div class="avatar avatar-lg bg-primary-lt">
                                <i class="ti ti-palette fs-2"></i>
                            </div>
                            <div class="min-w-0 flex-fill">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <h3 class="card-title mb-0"><?= esc($manager->displayName($theme)) ?></h3>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-primary-lt"><?= esc(lang('MCMS.themes.active')) ?></span>
                                    <?php endif ?>
                                </div>
                                <div class="text-secondary small mt-1">
                                    <?= esc(sprintf(lang('MCMS.themes.version'), $theme->version)) ?>
                                    <?php if ($theme->author !== null): ?>
                                        · <?= esc($theme->author) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($manager->displayDescription($theme) !== ''): ?>
                            <p class="text-secondary mt-3 mb-3"><?= esc($manager->displayDescription($theme)) ?></p>
                        <?php endif ?>

                        <div class="mt-auto">
                            <div class="small text-secondary mb-2">
                                <?= esc(sprintf(lang('MCMS.themes.templatesCount'), count($theme->templates))) ?>
                                ·
                                <?= esc(sprintf(lang('MCMS.themes.menuLocationsCount'), count($theme->menuLocations))) ?>
                            </div>

                            <?php if ($theme->menuLocations !== []): ?>
                                <div class="mb-3">
                                    <?php foreach ($theme->menuLocations as $locationId => $location): ?>
                                        <span class="badge bg-secondary-lt me-1 mb-1">
                                            <?= esc($manager->displayLocationLabel($location)) ?>
                                            <?php if (($location['max_depth'] ?? null) !== null): ?>
                                                · <?= esc(sprintf(lang('MCMS.themes.depth'), (int) $location['max_depth'])) ?>
                                            <?php endif ?>
                                        </span>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>

                            <?php if (! $isActive): ?>
                                <form method="post" action="<?= site_url('admin/themes/' . $theme->id . '/activate') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="ti ti-check me-1"></i><?= esc(lang('MCMS.themes.activate')) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-outline-primary" type="button" disabled>
                                    <i class="ti ti-check me-1"></i><?= esc(lang('MCMS.themes.active')) ?>
                                </button>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>