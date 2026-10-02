<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3"><h2 class="page-title"><?= esc(lang($album === null ? 'Galleries.Admin.create' : 'Galleries.Admin.edit')) ?></h2><a href="<?= site_url('admin/galleries') ?>" class="btn btn-link"><?= esc(lang('MCMS.common.back')) ?></a></div>
<?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif ?>
<form method="post">
    <?= csrf_field() ?>
    <?php if ($album !== null): ?><div class="alert alert-info"><label class="form-label" for="gallery-shortcode"><?= esc(lang('Galleries.Admin.shortcode')) ?></label><input class="form-control font-monospace" id="gallery-shortcode" readonly value="<?= esc('[gallery id="' . (int) $album['id'] . '"]', 'attr') ?>"><div class="mt-2"><?= esc(lang('Galleries.Admin.shortcodeHint')) ?></div></div><?php endif ?>
    <div class="card mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label" for="gallery-title"><?= esc(lang('MCMS.common.title')) ?></label><input class="form-control" id="gallery-title" name="title" maxlength="255" value="<?= esc($values['title'] ?? '', 'attr') ?>" required></div>
            <div class="col-md-4"><label class="form-label" for="gallery-status"><?= esc(lang('MCMS.common.status')) ?></label><select class="form-select" id="gallery-status" name="status">
                <?php foreach (['draft', 'published'] as $status): ?><option value="<?= $status ?>" <?= ($values['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= esc(lang('Galleries.Admin.' . $status)) ?></option><?php endforeach ?>
            </select></div>
            <div class="col-12"><label class="form-label" for="gallery-slug"><?= esc(lang('Galleries.Admin.slug')) ?></label><input class="form-control" id="gallery-slug" name="slug" maxlength="190" value="<?= esc($values['slug'] ?? '', 'attr') ?>"><div class="form-hint"><?= esc(lang('Galleries.Admin.slugHint')) ?></div></div>
            <div class="col-12"><label class="form-label" for="gallery-description"><?= esc(lang('Galleries.Admin.albumDescription')) ?></label><textarea class="form-control" id="gallery-description" name="description" rows="3" maxlength="20000"><?= esc($values['description'] ?? '') ?></textarea></div>
        </div>
    </div></div>
    <div class="card mb-3"><div class="card-header"><h3 class="card-title"><?= esc(lang('Galleries.Admin.images')) ?></h3></div><div class="card-body">
        <p class="text-secondary"><?= esc(lang('Galleries.Admin.orderHint')) ?></p>
        <label class="form-check mb-3"><input type="radio" class="form-check-input" name="cover_media_id" value="0" <?= empty($values['cover_media_id']) ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Galleries.Admin.automaticCover')) ?></span></label>
        <div id="selected-images" class="row g-3">
            <?php foreach ($selected as $image): ?>
                <div class="col-sm-6 col-xl-4" data-selected-id="<?= (int) $image['media_id'] ?>"><div class="border rounded p-3 h-100">
                    <?php if ($image['media'] !== null): ?><img class="img-fluid rounded mb-2" style="height:140px;object-fit:contain" src="<?= esc($image['media']['thumbnail_url'], 'attr') ?>" alt="<?= esc($image['media']['alt_text'] ?? '', 'attr') ?>" loading="lazy"><?php endif ?>
                    <p class="text-break"><?= esc($image['media']['title'] ?? $image['media']['original_name'] ?? lang('Galleries.Admin.missing')) ?></p>
                    <input type="hidden" name="media_ids[]" value="<?= (int) $image['media_id'] ?>">
                    <label class="form-label"><?= esc(lang('Galleries.Admin.caption')) ?><input class="form-control mb-2" name="captions[<?= (int) $image['media_id'] ?>]" maxlength="500" value="<?= esc($image['caption'], 'attr') ?>"></label>
                    <label class="form-label"><?= esc(lang('Galleries.Admin.position')) ?><input class="form-control mb-2" type="number" min="0" name="positions[<?= (int) $image['media_id'] ?>]" value="<?= (int) $image['position'] ?>"></label>
                    <label class="form-check mb-2"><input class="form-check-input" type="radio" name="cover_media_id" value="<?= (int) $image['media_id'] ?>" <?= (int) ($values['cover_media_id'] ?? 0) === (int) $image['media_id'] ? 'checked' : '' ?>><span class="form-check-label"><?= esc(lang('Galleries.Admin.cover')) ?></span></label>
                    <button class="btn btn-outline-danger btn-sm" type="button" data-remove-image><?= esc(lang('Galleries.Admin.remove')) ?></button>
                </div></div>
            <?php endforeach ?>
        </div>
    </div></div>
    <div class="card mb-3"><div class="card-header"><h3 class="card-title"><?= esc(lang('Galleries.Admin.chooseImages')) ?></h3></div><div class="card-body">
        <div class="input-group mb-3"><input type="search" class="form-control" id="media-search" aria-label="<?= esc(lang('Galleries.Admin.mediaSearch'), 'attr') ?>" placeholder="<?= esc(lang('Galleries.Admin.mediaSearch'), 'attr') ?>"><button class="btn btn-outline-primary" type="button" id="search-media"><?= esc(lang('MCMS.common.search')) ?></button></div>
        <p id="media-status" class="text-secondary" role="status"></p><div class="row g-3" id="media-results"></div>
        <div class="d-flex justify-content-between align-items-center mt-3"><button type="button" class="btn btn-outline-secondary" id="media-previous"><?= esc(lang('MCMS.common.previousPage')) ?></button><span id="media-page"></span><button type="button" class="btn btn-outline-secondary" id="media-next"><?= esc(lang('MCMS.common.nextPage')) ?></button></div>
    </div></div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('Galleries.Admin.save')) ?></button>
</form>
<script>
(() => {
    const labels = <?= json_encode(array_combine(['caption','position','cover','remove','missing','loading','empty','loadError','add'], array_map(static fn ($key) => lang('Galleries.Admin.' . $key), ['caption','position','cover','remove','missing','loading','empty','loadError','add'])), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
    const selected = document.getElementById('selected-images');
    const selectedIds = new Set();
    let page = 1;
    let lastPage = 1;
    let request = 0;
    function add(item, caption = '', position = selectedIds.size, cover = false) {
        const id = Number(item.id);
        if (selectedIds.has(id)) return;
        selectedIds.add(id);
        const column = document.createElement('div');
        column.className = 'col-sm-6 col-xl-4';
        const card = document.createElement('div');
        card.className = 'border rounded p-3 h-100';
        if (item.thumbnail_url || item.url) {
            const img = document.createElement('img');
            img.src = item.thumbnail_url || item.url;
            img.alt = item.alt_text || '';
            img.className = 'img-fluid rounded mb-2';
            img.style.height = '140px';
            img.style.objectFit = 'contain';
            img.loading = 'lazy';
            card.append(img);
        }
        const title = document.createElement('p');
        title.className = 'text-break';
        title.textContent = item.title || item.original_name || labels.missing;
        card.append(title);
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'media_ids[]'; hidden.value = id; card.append(hidden);
        for (const [key, value, type] of [['captions', caption, 'text'], ['positions', position, 'number']]) {
            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = key === 'captions' ? labels.caption : labels.position;
            const input = document.createElement('input');
            input.className = 'form-control mb-2'; input.name = `${key}[${id}]`; input.type = type; input.value = value;
            if (type === 'text') input.maxLength = 500; else input.min = 0;
            label.append(input); card.append(label);
        }
        const coverLabel = document.createElement('label');
        coverLabel.className = 'form-check mb-2';
        const radio = document.createElement('input');
        radio.type = 'radio'; radio.className = 'form-check-input'; radio.name = 'cover_media_id'; radio.value = id; radio.checked = cover;
        const coverText = document.createElement('span'); coverText.className = 'form-check-label'; coverText.textContent = labels.cover;
        coverLabel.append(radio, coverText); card.append(coverLabel);
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = labels.remove;
        remove.addEventListener('click', () => {
            if (radio.checked) document.querySelector('[name="cover_media_id"][value="0"]').checked = true;
            selectedIds.delete(id); column.remove();
        });
        card.append(remove); column.append(card); selected.append(column);
    }
    selected.querySelectorAll('[data-selected-id]').forEach(column => {
        const id = Number(column.dataset.selectedId);
        selectedIds.add(id);
        column.querySelector('[data-remove-image]').addEventListener('click', () => {
            if (column.querySelector('[name="cover_media_id"]').checked) document.querySelector('[name="cover_media_id"][value="0"]').checked = true;
            selectedIds.delete(id); column.remove();
        });
    });
    async function load(targetPage) {
        const sequence = ++request;
        const status = document.getElementById('media-status');
        status.textContent = labels.loading;
        try {
            const url = new URL(<?= json_encode(site_url('admin/galleries/media')) ?>);
            url.searchParams.set('q', document.getElementById('media-search').value);
            url.searchParams.set('page', targetPage);
            const response = await fetch(url);
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (sequence !== request) return;
            page = data.page; lastPage = data.lastPage;
            const results = document.getElementById('media-results'); results.replaceChildren();
            status.textContent = data.items.length ? '' : labels.empty;
            data.items.forEach(item => {
                const column = document.createElement('div'); column.className = 'col-6 col-md-4 col-xl-3';
                const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-secondary w-100 flex-column text-wrap h-100';
                const img = document.createElement('img'); img.src = item.thumbnail_url || item.url; img.alt = item.alt_text || ''; img.className = 'img-fluid mb-2'; img.style.height = '100px'; img.style.objectFit = 'contain'; img.loading = 'lazy';
                const text = document.createElement('span'); text.textContent = item.title || item.original_name;
                button.append(img, text); button.title = labels.add;
                button.addEventListener('click', () => add(item)); column.append(button); results.append(column);
            });
            document.getElementById('media-page').textContent = `${page} / ${lastPage}`;
            document.getElementById('media-previous').disabled = page <= 1;
            document.getElementById('media-next').disabled = page >= lastPage;
        } catch (_) { if (sequence === request) status.textContent = labels.loadError; }
    }
    document.getElementById('search-media').addEventListener('click', () => load(1));
    document.getElementById('media-search').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); load(1); } });
    document.getElementById('media-previous').addEventListener('click', () => load(page - 1));
    document.getElementById('media-next').addEventListener('click', () => load(page + 1));
    load(1);
})();
</script>
<?= $this->endSection() ?>
