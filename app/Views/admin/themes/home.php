<form method="post" action="<?= $settingsUrl('admin/themes/' . $theme->id . '/home') ?>" class="card mb-4" id="theme-home-form">
    <?= csrf_field() ?>
    <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.themes.homeContent')) ?></h3></div>
    <div class="card-body">
        <p class="text-secondary"><?= esc(lang('MCMS.themes.homeHint')) ?></p>
        <p class="form-hint"><?= esc(lang('MCMS.themes.homePagePriority')) ?></p>
        <?php foreach (['eyebrow' => 120, 'title' => 180, 'description' => 600, 'button_label' => 80, 'button_url' => 1000] as $field => $limit): ?>
        <div class="mb-3">
            <label class="form-label" for="home-<?= $field ?>"><?= esc(lang('MCMS.themes.homeFields.' . $field)) ?></label>
            <?php if (in_array($field, ['title', 'description'], true)): ?>
                <textarea class="form-control" id="home-<?= $field ?>" name="home[<?= $field ?>]" rows="<?= $field === 'title' ? 2 : 3 ?>" maxlength="<?= $limit ?>" <?= $field === 'title' ? 'required' : '' ?>><?= esc(old('home.' . $field, $homeContent[$field])) ?></textarea>
            <?php else: ?>
                <input class="form-control" id="home-<?= $field ?>" name="home[<?= $field ?>]" maxlength="<?= $limit ?>" value="<?= esc(old('home.' . $field, $homeContent[$field]), 'attr') ?>">
            <?php endif ?>
            <?php if ($field === 'button_url'): ?><p class="form-hint"><?= esc(lang('MCMS.themes.homeUrlHint')) ?></p><?php endif ?>
        </div>
        <?php endforeach ?>
        <?php $value = (string) old('home.image_media_id', $homeContent['image_media_id'] ?? ''); $image = $value !== '' && (int) $value === (int) ($homeContent['image_media_id'] ?? 0) ? $homeContent['image'] : null; ?>
        <label class="form-label" for="home-image"><?= esc(lang('MCMS.themes.homeCover')) ?></label>
        <input id="home-image" data-image-input="home" type="hidden" name="home[image_media_id]" value="<?= esc($value, 'attr') ?>">
        <div class="border rounded p-3 mb-2" data-image-preview="home" data-image-height="160px">
            <img src="<?= esc($image !== null ? site_url(ltrim($image['url'], '/')) : $manager->assetUrl('images/hero.webp', $theme), 'attr') ?>" alt="<?= esc($image['title'] ?? '', 'attr') ?>" style="max-height:160px;max-width:100%">
        </div>
        <div class="d-flex gap-2 flex-wrap mb-4"><button type="button" class="btn btn-outline-primary btn-sm" data-theme-image="home" <?= $imagesAvailable ? '' : 'disabled' ?>><?= esc(lang('MCMS.themes.chooseImage')) ?></button><button type="button" class="btn btn-outline-secondary btn-sm" data-image-clear="home"><?= esc(lang('MCMS.themes.defaultImage')) ?></button></div>
        <?php if (! $imagesAvailable): ?><p class="text-secondary"><?= esc(lang('MCMS.themes.imagesUnavailable')) ?></p><?php endif ?>
        <h4 class="mt-4"><?= esc(lang('MCMS.themes.homeSections')) ?></h4>
        <?php foreach (['intro_title' => 180, 'intro_text' => 600] as $field => $limit): ?>
        <div class="mb-3"><label class="form-label" for="home-<?= $field ?>"><?= esc(lang('MCMS.themes.homeFields.' . $field)) ?></label><textarea class="form-control" id="home-<?= $field ?>" name="home[<?= $field ?>]" rows="2" maxlength="<?= $limit ?>"><?= esc(old('home.' . $field, $homeContent[$field])) ?></textarea></div>
        <?php endforeach ?>
        <?php foreach (['show_news', 'show_galleries'] as $field): ?>
        <input type="hidden" name="home[<?= $field ?>]" value="0">
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="home[<?= $field ?>]" value="1" <?= old('home.' . $field, $homeContent[$field]) ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('MCMS.themes.homeFields.' . $field)) ?></span></label>
        <?php endforeach ?>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center gap-3 flex-wrap"><a class="btn btn-link px-0" href="<?= site_url('/') ?>" target="_blank" rel="noopener"><?= esc(lang('MCMS.themes.openSite')) ?> ↗</a><button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button></div>
</form>
