<form method="post" action="<?= site_url('admin/themes/' . $theme->id . '/appearance') ?>" class="card mb-4" id="theme-appearance-form">
    <?= csrf_field() ?>
    <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.themes.appearance')) ?></h3></div>
    <div class="card-body">
        <p class="text-secondary"><?= esc(lang('MCMS.themes.appearanceHint')) ?></p>
        <div class="row g-4">
        <?php foreach (['logo', 'favicon'] as $field): ?>
            <?php $value = (string) old('appearance.' . $field . '_media_id', $appearance[$field . '_media_id'] ?? ''); $image = $value !== '' && (int) $value === (int) ($appearance[$field . '_media_id'] ?? 0) ? $appearance[$field] : null; ?>
            <div class="col-sm-6">
                <label class="form-label" for="appearance-<?= $field ?>"><?= esc(lang('MCMS.themes.' . $field)) ?></label>
                <input id="appearance-<?= $field ?>" type="hidden" name="appearance[<?= $field ?>_media_id]" value="<?= esc($value, 'attr') ?>">
                <div class="border rounded p-3 mb-2" style="min-height:85px" data-image-preview="<?= $field ?>">
                    <?php if ($image !== null): ?><img src="<?= esc(site_url(ltrim($image['url'], '/')), 'attr') ?>" alt="<?= esc($image['title'], 'attr') ?>" style="max-height:64px;max-width:100%"><?php else: ?><span class="text-secondary"><?= esc(lang('MCMS.themes.defaultImage')) ?></span><?php endif ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-theme-image="<?= $field ?>" <?= $imagesAvailable ? '' : 'disabled' ?>><?= esc(lang('MCMS.themes.chooseImage')) ?></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-image-clear="<?= $field ?>"><?= esc(lang('MCMS.themes.defaultImage')) ?></button>
                </div>
            </div>
        <?php endforeach ?>
        </div>
        <?php if (! $imagesAvailable): ?><p class="text-secondary mt-2"><?= esc(lang('MCMS.themes.imagesUnavailable')) ?></p><?php endif ?>
        <div class="mt-4">
            <label class="form-label" for="appearance-accent"><?= esc(lang('MCMS.themes.accent')) ?></label>
            <div class="d-flex gap-2 align-items-center">
                <input type="color" class="form-control form-control-color" aria-label="<?= esc(lang('MCMS.themes.accent'), 'attr') ?>" data-accent-picker value="<?= esc($appearance['accent'] ?: '#2563eb', 'attr') ?>">
                <input class="form-control" id="appearance-accent" name="appearance[accent]" value="<?= esc(old('appearance.accent', $appearance['accent']), 'attr') ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#2563eb">
                <button type="button" class="btn btn-outline-secondary" data-accent-clear><?= esc(lang('MCMS.themes.reset')) ?></button>
            </div>
            <p class="form-hint"><?= esc(lang('MCMS.themes.accentHint')) ?></p>
        </div>
        <h4 class="mt-4"><?= esc(lang('MCMS.themes.contacts')) ?></h4>
        <?php foreach (['phone' => 80, 'email' => 255, 'address' => 500] as $field => $limit): ?>
        <div class="mb-3">
            <label class="form-label" for="appearance-<?= $field ?>"><?= esc(lang('MCMS.themes.' . $field)) ?></label>
            <input class="form-control" id="appearance-<?= $field ?>" name="appearance[<?= $field ?>]" type="<?= $field === 'email' ? 'email' : 'text' ?>" maxlength="<?= $limit ?>" value="<?= esc(old('appearance.' . $field, $appearance[$field]), 'attr') ?>">
        </div>
        <?php endforeach ?>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button></div>
</form>
<dialog id="theme-image-dialog" aria-labelledby="theme-image-heading" style="width:min(900px,94vw);max-height:85vh;border:0;border-radius:12px;background:var(--tblr-bg-surface);color:var(--tblr-body-color)">
    <div class="d-flex justify-content-between align-items-center mb-3"><h3 id="theme-image-heading"><?= esc(lang('MCMS.themes.chooseImage')) ?></h3><button class="btn-close" type="button" data-theme-image-close aria-label="<?= esc(lang('MCMS.common.cancel'), 'attr') ?>"></button></div>
    <form class="d-flex gap-2 mb-3" data-image-search><input class="form-control" name="q" aria-label="<?= esc(lang('MCMS.common.search'), 'attr') ?>" placeholder="<?= esc(lang('MCMS.common.search'), 'attr') ?>"><button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.search')) ?></button></form>
    <p role="status" data-image-status></p><div class="row g-3" data-image-items></div><button class="btn btn-outline-secondary mt-3" type="button" data-image-more hidden><?= esc(lang('MCMS.content.moreVersions')) ?></button>
</dialog>
<script>
(() => {
    const form = document.getElementById('theme-appearance-form');
    const dialog = document.getElementById('theme-image-dialog');
    const items = dialog.querySelector('[data-image-items]');
    const status = dialog.querySelector('[data-image-status]');
    const more = dialog.querySelector('[data-image-more]');
    const search = dialog.querySelector('[data-image-search]');
    const defaultLabel = <?= json_encode(lang('MCMS.themes.defaultImage')) ?>;
    let field, trigger, offset = 0, requestId = 0;
    function select(image) {
        const input = form.querySelector('#appearance-' + field);
        input.value = image.id;
        const preview = form.querySelector('[data-image-preview="' + field + '"]');
        const img = document.createElement('img'); img.src = image.url; img.alt = image.title;
        img.style.maxHeight = '64px'; img.style.maxWidth = '100%'; preview.replaceChildren(img);
        dialog.close();
    }
    async function load(reset = false) {
        const current = ++requestId;
        if (reset) {offset = 0; items.replaceChildren();}
        more.disabled = true;
        status.textContent = <?= json_encode(lang('MCMS.themes.loadingImages')) ?>;
        try {
            const response = await fetch(<?= json_encode(site_url('admin/themes/images')) ?> + '?q=' + encodeURIComponent(search.elements.q.value) + '&offset=' + offset, {credentials:'same-origin',cache:'no-store'});
            const data = await response.json();
            if (current !== requestId || !dialog.open) return;
            if (!response.ok) throw new Error(data.error);
            data.items.forEach(image => {
                const col = document.createElement('div'); col.className = 'col-6 col-md-3';
                const button = document.createElement('button'); button.type = 'button'; button.className = 'btn border w-100 d-flex flex-column p-2';
                const img = document.createElement('img'); img.src = image.thumbnail_url; img.alt = ''; img.style.height = '100px'; img.style.objectFit = 'contain'; img.style.maxWidth = '100%';
                const label = document.createElement('span'); label.className = 'small mt-2 text-break'; label.textContent = image.title;
                button.append(img,label); button.addEventListener('click', () => select(image)); col.append(button); items.append(col);
            });
            offset += data.items.length; more.hidden = offset >= data.count;
            status.textContent = data.count ? '' : <?= json_encode(lang('MCMS.themes.noImages')) ?>;
        } catch (failure) {if (current === requestId) status.textContent = failure.message;}
        finally {if (current === requestId) more.disabled = false;}
    }
    form.querySelectorAll('[data-theme-image]').forEach(button => button.addEventListener('click', () => {field = button.dataset.themeImage; trigger = button; search.reset(); dialog.showModal(); load(true);}));
    form.querySelectorAll('[data-image-clear]').forEach(button => button.addEventListener('click', () => {
        form.querySelector('#appearance-' + button.dataset.imageClear).value = '';
        const text = document.createElement('span'); text.className = 'text-secondary'; text.textContent = defaultLabel;
        form.querySelector('[data-image-preview="' + button.dataset.imageClear + '"]').replaceChildren(text);
    }));
    dialog.querySelector('[data-theme-image-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {++requestId; trigger?.focus();});
    search.addEventListener('submit', event => {event.preventDefault(); load(true);});
    more.addEventListener('click', () => load());
    const accent = form.querySelector('#appearance-accent');
    const picker = form.querySelector('[data-accent-picker]');
    picker.addEventListener('input', () => accent.value = picker.value);
    accent.addEventListener('input', () => {if (/^#[0-9a-f]{6}$/i.test(accent.value)) picker.value = accent.value;});
    form.querySelector('[data-accent-clear]').addEventListener('click', () => accent.value = '');
})();
</script>
