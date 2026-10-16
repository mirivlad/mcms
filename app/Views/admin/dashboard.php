<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <h2 class="page-title"><?= esc(lang('MCMS.dashboard.title')) ?></h2>
            <div class="text-secondary mt-1"><?= esc(lang('MCMS.dashboard.description')) ?></div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <?php
    $overviewMenu = array_values(array_filter($adminMenu ?? [], static fn ($item) => $item['id'] !== 'dashboard'));
    foreach ($overviewMenu as &$item) {
        if (($item['overviewSection'] ?? null) !== null) {
            $item['group'] = 'administration';
            $item['section'] = $item['overviewSection'];
        }
    }
    unset($item);
    $overviewGroups = service('mcmsAdmin')->groupedMenu($overviewMenu);
    ?>
    <?php foreach ($overviewGroups as $group): ?>
    <?php foreach ($group['sections'] ?? [$group] as $section): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><?= esc($section['label'] !== '' ? lang(substr($section['label'], 1)) : lang('MCMS.dashboard.sections')) ?></h3></div>
            <div class="list-group list-group-flush">
                <?php foreach ($section['items'] as $item): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= site_url(ltrim($item['url'], '/')) ?>">
                    <i class="<?= esc($item['icon'], 'attr') ?>" aria-hidden="true"></i><span><?= esc($item['label']) ?></span>
                    <i class="ti ti-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <?php endforeach ?>
    <?php endforeach ?>

    <?php if ($overviewGroups === [] && ($widgets ?? []) === []): ?>
    <div class="col-12"><div class="card"><div class="card-body text-secondary"><?= esc(lang('MCMS.dashboard.empty')) ?></div></div></div>
    <?php endif ?>

    <?php foreach ($widgets ?? [] as $widget): ?>
        <div class="col-sm-6 col-lg-4">
            <?= view($widget['view'], $widget['data']) ?>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
