<?php
$settingsGroups = array_fill_keys(['general', 'appearance', 'content', 'commerce', 'integrations'], []);
foreach (service('mcmsAdmin')->settings() as $section) {
    if (auth()->user()?->can($section['permission'])) {
        $settingsGroups[$section['group']][] = $section;
    }
}
$settingsRequest = '/' . trim(service('request')->getUri()->getPath(), '/');
?>
<nav class="card mb-4" aria-label="<?= esc(lang('Settings.title'), 'attr') ?>">
    <div class="card-header"><h2 class="card-title"><?= esc(lang('Settings.title')) ?></h2></div>
    <div class="card-body row g-3">
    <?php foreach ($settingsGroups as $group => $sections): if ($sections === []) continue; ?>
        <div class="col-12 col-sm-6 col-xl">
            <div class="text-secondary small mb-2"><?= esc(lang('Settings.groups.' . $group)) ?></div>
            <div class="d-flex flex-wrap gap-2">
            <?php foreach ($sections as $section): ?>
                <?php $selected = $settingsRequest === $section['centralUrl'] || ($section['id'] !== 'general' && str_starts_with($settingsRequest, $section['centralUrl'] . '/')); ?>
                <a class="btn btn-sm <?= $selected ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= site_url(ltrim($section['centralUrl'], '/')) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><i class="<?= esc($section['icon'], 'attr') ?> me-1" aria-hidden="true"></i><?= esc(str_starts_with($section['label'], '@') ? lang(substr($section['label'], 1)) : $section['label']) ?></a>
            <?php endforeach ?>
            </div>
        </div>
    <?php endforeach ?>
    </div>
</nav>
