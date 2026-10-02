<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
    $isEdit = $article !== null;
    $titleValue = (string) old('title', $article['title'] ?? '');
    $slugValue = (string) old('slug', $article['slug'] ?? '');
    $excerptValue = (string) old('excerpt', $article['excerpt'] ?? '');
    $sanitizer = new \MCMS\Core\Content\HtmlSanitizer();
    $bodyValue = $sanitizer->sanitize((string) old('body', $article['body'] ?? ''));
    $statusValue = (string) old('status', $article['status'] ?? $defaultStatus);
    $publishedValue = (string) old('published_at', $article['published_at'] ?? '');
    $publishedTimestamp = $publishedValue === '' ? false : strtotime($publishedValue);
    $publishedInput = $publishedTimestamp === false ? '' : date('Y-m-d\\TH:i', $publishedTimestamp);
    $featuredId = (string) old('featured_media_id', $article['featured_media_id'] ?? '');
    $featuredUrl = $article !== null
        && $featuredId !== ''
        && (int) $featuredId === (int) ($article['featured_media_id'] ?? 0)
        ? (string) ($article['featured_image_url'] ?? '')
        : '';
    $seoTitle = (string) old('seo_title', $article['seo_title'] ?? '');
    $seoDescription = (string) old('seo_description', $article['seo_description'] ?? '');
    $seoOpen = $seoTitle !== '' || $seoDescription !== '';
?>

<style>
.mcms-editor-shell {
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    overflow: hidden;
    background: var(--tblr-bg-surface);
}
.mcms-editor-toolbar {
    display: flex;
    flex-wrap: nowrap;
    gap: .25rem;
    padding: .5rem;
    overflow-x: auto;
    border-bottom: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface-secondary);
}
.mcms-editor-toolbar .btn {
    flex: 0 0 2.25rem;
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
}
.mcms-editor-toolbar-separator {
    flex: 0 0 1px;
    width: 1px;
    align-self: stretch;
    margin: 0 .25rem;
    background: var(--tblr-border-color);
}
.mcms-editor {
    min-height: 30rem;
    padding: 1.25rem;
    outline: none;
    overflow: auto;
    line-height: 1.65;
}
.mcms-editor:focus {
    box-shadow: inset 0 0 0 1px var(--tblr-primary);
}
.mcms-editor img {
    max-width: 100%;
    height: auto;
}
.mcms-seo-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    list-style: none;
}
.mcms-seo-summary > div {
    flex: 1 1 auto;
    min-width: 0;
}
.mcms-seo-summary::-webkit-details-marker {
    display: none;
}
.mcms-seo-chevron {
    flex: 0 0 auto;
    transition: transform .15s ease;
}
details:not([open]) > .mcms-seo-summary .mcms-seo-chevron {
    transform: rotate(-90deg);
}
.mcms-permalink-group {
    flex-wrap: nowrap;
    overflow: hidden;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface, #fff);
}
.mcms-permalink-group .input-group-text,
.mcms-permalink-group .form-control {
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent;
}
.mcms-permalink-group .form-control {
    min-width: 0;
    border-left: 1px solid var(--tblr-border-color) !important;
}
.mcms-permalink-group:focus-within {
    border-color: var(--tblr-primary);
}
#news-media-dialog {
    width: min(1000px, 94vw);
    max-height: 88vh;
}
#news-media-dialog::backdrop {
    background: rgba(0, 0, 0, .45);
}
.news-featured-preview {
    aspect-ratio: 16 / 9;
    width: 100%;
    object-fit: cover;
}
@media (max-width: 767.98px) {
    .mcms-editor {
        min-height: 22rem;
        padding: 1rem;
    }
    .mcms-editor-toolbar {
        scrollbar-width: none;
    }
    .mcms-editor-toolbar::-webkit-scrollbar {
        display: none;
    }
}
</style>

<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('News.Admin.title')) ?></div>
            <h2 class="page-title"><?= esc(lang($isEdit ? 'News.Admin.edit' : 'News.Admin.create')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
            <?php if ($isEdit && ($article['is_live'] ?? false)): ?>
                <a class="btn btn-outline-secondary" href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>" target="_blank" rel="noopener">
                    <i class="ti ti-external-link me-1"></i><?= esc(lang('News.Admin.viewPublic')) ?>
                </a>
            <?php endif ?>
            <a class="btn btn-outline-secondary d-none d-md-inline-flex" href="<?= site_url('admin/news') ?>">
                <i class="ti ti-arrow-left me-1"></i><?= esc(lang('MCMS.common.back')) ?>
            </a>
        </div>
    </div>
</div>

<form method="post" id="news-form">
    <?= csrf_field() ?>
    <div class="row row-cards">
        <div class="col-xl-9">
            <div class="card mb-3">
                <div class="card-body">
                    <input
                        class="form-control form-control-lg mb-3"
                        id="news-title"
                        name="title"
                        maxlength="255"
                        required
                        placeholder="<?= esc(lang('MCMS.common.title'), 'attr') ?>"
                        value="<?= esc($titleValue) ?>"
                    >

                    <label class="form-label" for="news-slug"><?= esc(lang('News.Admin.permalink')) ?></label>
                    <div class="input-group mcms-permalink-group">
                        <span class="input-group-text"><i class="ti ti-link"></i><span class="ms-2">/news/</span></span>
                        <input
                            class="form-control font-monospace"
                            id="news-slug"
                            name="slug"
                            maxlength="190"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            value="<?= esc($slugValue) ?>"
                        >
                    </div>
                    <div class="form-hint"><?= esc(lang('News.Admin.slugHint')) ?></div>

                    <div class="mt-3">
                        <label class="form-label" for="news-excerpt"><?= esc(lang('News.Admin.excerpt')) ?></label>
                        <textarea class="form-control" id="news-excerpt" name="excerpt" rows="3" maxlength="500"><?= esc($excerptValue) ?></textarea>
                        <div class="form-hint"><?= esc(lang('News.Admin.excerptHint')) ?></div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= esc(lang('News.Admin.body')) ?></label>
                <div class="mcms-editor-shell">
                    <div class="mcms-editor-toolbar" id="news-editor-toolbar">
                        <button class="btn btn-ghost-secondary" type="button" data-command="bold" title="<?= esc(lang('News.Admin.bold'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.bold'), 'attr') ?>"><i class="ti ti-bold"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="italic" title="<?= esc(lang('News.Admin.italic'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.italic'), 'attr') ?>"><i class="ti ti-italic"></i></button>
                        <span class="mcms-editor-toolbar-separator"></span>
                        <button class="btn btn-ghost-secondary" type="button" data-format="h2" title="<?= esc(lang('News.Admin.heading'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.heading'), 'attr') ?>"><i class="ti ti-heading"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-format="p" title="<?= esc(lang('News.Admin.paragraph'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.paragraph'), 'attr') ?>"><i class="ti ti-letter-case"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="insertUnorderedList" title="<?= esc(lang('News.Admin.bulletList'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.bulletList'), 'attr') ?>"><i class="ti ti-list"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="insertOrderedList" title="<?= esc(lang('News.Admin.numberedList'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.numberedList'), 'attr') ?>"><i class="ti ti-list-numbers"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-format="blockquote" title="<?= esc(lang('News.Admin.quote'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.quote'), 'attr') ?>"><i class="ti ti-blockquote"></i></button>
                        <span class="mcms-editor-toolbar-separator"></span>
                        <button class="btn btn-ghost-secondary" type="button" id="news-editor-link" title="<?= esc(lang('News.Admin.link'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.link'), 'attr') ?>"><i class="ti ti-link"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="removeFormat" title="<?= esc(lang('News.Admin.clearFormatting'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.clearFormatting'), 'attr') ?>"><i class="ti ti-clear-formatting"></i></button>
                        <span class="mcms-editor-toolbar-separator"></span>
                        <button class="btn btn-ghost-primary" type="button" id="news-insert-media" title="<?= esc(lang('News.Admin.insertImage'), 'attr') ?>" aria-label="<?= esc(lang('News.Admin.insertImage'), 'attr') ?>"><i class="ti ti-photo-plus"></i></button>
                        <?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
                        <button class="btn btn-ghost-primary" type="button" data-gallery-picker data-editor="news-editor" aria-haspopup="dialog" aria-controls="content-gallery-dialog" title="<?= esc(lang('Galleries.Admin.insertGallery'), 'attr') ?>" aria-label="<?= esc(lang('Galleries.Admin.insertGallery'), 'attr') ?>">
                            <i class="ti ti-photo"></i><span class="ms-1"><?= esc(lang('Galleries.Admin.galleryButton')) ?></span>
                        </button>
                        <?php endif ?>
                    </div>
                    <div id="news-editor" class="mcms-editor" contenteditable="true" role="textbox" aria-multiline="true"><?= $bodyValue ?></div>
                </div>
                <?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?><p class="form-hint mt-2"><?= esc(lang('News.Admin.galleryHint')) ?></p><?php endif ?>
                <textarea class="d-none" id="news-body" name="body"><?= esc($bodyValue) ?></textarea>
            </div>

            <details class="card" <?= $seoOpen ? 'open' : '' ?>>
                <summary class="card-header mcms-seo-summary">
                    <div>
                        <h3 class="card-title mb-0"><i class="ti ti-world-search me-2"></i><?= esc(lang('News.Admin.seo')) ?></h3>
                        <div class="text-secondary small mt-1"><?= esc(lang('News.Admin.seoHint')) ?></div>
                    </div>
                    <i class="ti ti-chevron-down mcms-seo-chevron text-secondary" aria-hidden="true"></i>
                </summary>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="seo-title"><?= esc(lang('News.Admin.seoTitle')) ?></label>
                        <input class="form-control" id="seo-title" name="seo_title" maxlength="255" value="<?= esc($seoTitle) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="seo-description"><?= esc(lang('News.Admin.seoDescription')) ?></label>
                        <textarea class="form-control" id="seo-description" name="seo_description" maxlength="320" rows="3"><?= esc($seoDescription) ?></textarea>
                    </div>
                </div>
            </details>
        </div>

        <div class="col-xl-3">
            <div class="position-sticky" style="top:1rem">
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-send me-2"></i><?= esc(lang('MCMS.common.status')) ?></h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <select class="form-select" name="status">
                                <option value="draft" <?= $statusValue === 'draft' ? 'selected' : '' ?>><?= esc(lang('News.Admin.statusDraft')) ?></option>
                                <option value="published" <?= $statusValue === 'published' ? 'selected' : '' ?>><?= esc(lang('News.Admin.statusPublished')) ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="published-at"><?= esc(lang('News.Admin.publishedAt')) ?></label>
                            <input class="form-control" type="datetime-local" id="published-at" name="published_at" value="<?= esc($publishedInput, 'attr') ?>">
                            <div class="form-hint"><?= esc(lang('News.Admin.publishedAtHint')) ?></div>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="mb-3">
                                <div class="form-label"><?= esc(lang('News.Admin.permalink')) ?></div>
                                <a class="text-break" href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>" target="_blank" rel="noopener"><?= esc($article['url']) ?></a>
                            </div>
                        <?php endif ?>
                        <button class="btn btn-primary w-100" type="submit"><i class="ti ti-device-floppy me-1"></i><?= esc(lang($isEdit ? 'News.Admin.saveSubmit' : 'News.Admin.createSubmit')) ?></button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title"><i class="ti ti-photo me-2"></i><?= esc(lang('News.Admin.featuredImage')) ?></h3></div>
                    <div class="card-body">
                        <input type="hidden" name="featured_media_id" id="featured-media-id" value="<?= esc($featuredId, 'attr') ?>">
                        <div id="featured-preview" class="mb-3 <?= $featuredUrl === '' ? 'd-none' : '' ?>">
                            <img id="featured-preview-image" class="news-featured-preview rounded border" src="<?= $featuredUrl === '' ? '' : esc(site_url(ltrim($featuredUrl, '/')), 'attr') ?>" alt="">
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" id="choose-featured" type="button"><i class="ti ti-photo-search me-1"></i><?= esc(lang('News.Admin.selectFeatured')) ?></button>
                            <button class="btn btn-outline-secondary <?= $featuredId === '' ? 'd-none' : '' ?>" id="remove-featured" type="button"><i class="ti ti-x me-1"></i><?= esc(lang('News.Admin.removeFeatured')) ?></button>
                        </div>
                        <div class="form-hint mt-2"><?= esc(lang('News.Admin.featuredImageHint')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<dialog id="news-media-dialog" class="border-0 rounded shadow-lg p-0">
    <div class="card mb-0">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-photo me-2"></i><?= esc(lang('News.Admin.mediaPicker')) ?></h3>
            <button class="btn btn-icon btn-ghost-secondary ms-auto" type="button" id="close-media" aria-label="<?= esc(lang('News.Admin.closeMedia'), 'attr') ?>"><i class="ti ti-x"></i></button>
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input class="form-control" id="media-search" type="search" placeholder="<?= esc(lang('News.Admin.mediaSearch'), 'attr') ?>">
                <button class="btn btn-outline-primary" id="media-search-button" type="button"><?= esc(lang('MCMS.common.search')) ?></button>
            </div>
            <div id="media-loading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="text-secondary"><?= esc(lang('News.Admin.mediaLoading')) ?></div>
            </div>
            <div id="media-error" class="alert alert-danger d-none">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-alert-circle"></i>
                    <span class="flex-fill"><?= esc(lang('News.Admin.mediaLoadError')) ?></span>
                    <button class="btn btn-outline-danger btn-sm" type="button" id="media-retry"><i class="ti ti-refresh me-1"></i><?= esc(lang('News.Admin.retry')) ?></button>
                </div>
            </div>
            <div id="media-results" class="row row-cards"></div>
            <div id="media-empty" class="empty d-none">
                <div class="empty-img"><i class="ti ti-photo-off" style="font-size:4rem"></i></div>
                <p class="empty-title"><?= esc(lang('News.Admin.mediaEmpty')) ?></p>
            </div>
        </div>
    </div>
</dialog>

<script>
(() => {
    const form = document.getElementById('news-form');
    const editor = document.getElementById('news-editor');
    const body = document.getElementById('news-body');
    const toolbar = document.getElementById('news-editor-toolbar');
    const dialog = document.getElementById('news-media-dialog');
    const results = document.getElementById('media-results');
    const empty = document.getElementById('media-empty');
    const loading = document.getElementById('media-loading');
    const error = document.getElementById('media-error');
    const search = document.getElementById('media-search');
    const featuredId = document.getElementById('featured-media-id');
    const featuredPreview = document.getElementById('featured-preview');
    const featuredImage = document.getElementById('featured-preview-image');
    const removeFeatured = document.getElementById('remove-featured');
    const mediaEndpoint = <?= json_encode(site_url('admin/news/media'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const insertLabel = <?= json_encode(lang('News.Admin.insertImage'), JSON_UNESCAPED_UNICODE) ?>;
    const featuredLabel = <?= json_encode(lang('News.Admin.useFeatured'), JSON_UNESCAPED_UNICODE) ?>;
    let pickerMode = 'inline';
    let savedRange = null;

    const syncBody = () => {
        body.value = editor.innerHTML;
    };

    const rememberSelection = () => {
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };

    const restoreSelection = () => {
        editor.focus();
        if (! savedRange) return;
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
    };

    form.addEventListener('submit', syncBody);
    editor.addEventListener('input', syncBody);
    editor.addEventListener('keyup', rememberSelection);
    editor.addEventListener('mouseup', rememberSelection);
    toolbar.addEventListener('mousedown', rememberSelection);

    document.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            document.execCommand(button.dataset.command, false);
            rememberSelection();
            syncBody();
        });
    });

    document.querySelectorAll('[data-format]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            document.execCommand('formatBlock', false, button.dataset.format);
            rememberSelection();
            syncBody();
        });
    });

    document.getElementById('news-editor-link').addEventListener('click', () => {
        restoreSelection();
        const url = window.prompt('URL');
        if (! url) return;
        document.execCommand('createLink', false, url);
        rememberSelection();
        syncBody();
    });

    const setFeatured = (item) => {
        featuredId.value = String(item.id);
        featuredImage.src = item.thumbnail_url || item.url;
        featuredPreview.classList.remove('d-none');
        removeFeatured.classList.remove('d-none');
    };

    const insertInlineImage = (item) => {
        restoreSelection();
        const image = document.createElement('img');
        image.src = item.url;
        image.alt = item.alt_text || item.title || item.original_name || '';
        image.className = 'img-fluid';
        const paragraph = document.createElement('p');
        paragraph.appendChild(image);
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(paragraph);
            range.setStartAfter(paragraph);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        } else {
            editor.appendChild(paragraph);
        }
        rememberSelection();
        syncBody();
    };

    const chooseItem = (item) => {
        if (pickerMode === 'featured') {
            setFeatured(item);
        } else {
            insertInlineImage(item);
        }
        dialog.close();
    };

    const renderItems = (items) => {
        results.replaceChildren();
        empty.classList.toggle('d-none', items.length !== 0);
        items.forEach((item) => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-xl-3';
            const card = document.createElement('div');
            card.className = 'card h-100';
            const img = document.createElement('img');
            img.className = 'card-img-top';
            img.style.aspectRatio = '4 / 3';
            img.style.objectFit = 'cover';
            img.src = item.thumbnail_url || item.url;
            img.alt = item.alt_text || '';
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-2';
            const title = document.createElement('div');
            title.className = 'small text-truncate mb-2';
            title.textContent = item.title || item.original_name || ('#' + item.id);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-primary btn-sm w-100';
            button.textContent = pickerMode === 'featured' ? featuredLabel : insertLabel;
            button.addEventListener('click', () => chooseItem(item));
            cardBody.append(title, button);
            card.append(img, cardBody);
            col.append(card);
            results.append(col);
        });
    };

    const loadMedia = async () => {
        loading.classList.remove('d-none');
        error.classList.add('d-none');
        empty.classList.add('d-none');
        results.replaceChildren();
        try {
            const url = new URL(mediaEndpoint, window.location.href);
            if (search.value.trim()) url.searchParams.set('q', search.value.trim());
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            if (! response.ok) throw new Error('media');
            const payload = await response.json();
            renderItems(Array.isArray(payload.items) ? payload.items : []);
        } catch (_) {
            error.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
        }
    };

    const openPicker = (mode) => {
        pickerMode = mode;
        if (mode === 'inline') rememberSelection();
        dialog.showModal();
        loadMedia();
    };

    document.getElementById('news-insert-media').addEventListener('click', () => openPicker('inline'));
    document.getElementById('choose-featured').addEventListener('click', () => openPicker('featured'));
    document.getElementById('close-media').addEventListener('click', () => dialog.close());
    document.getElementById('media-retry').addEventListener('click', loadMedia);
    document.getElementById('media-search-button').addEventListener('click', loadMedia);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadMedia();
        }
    });
    removeFeatured.addEventListener('click', () => {
        featuredId.value = '';
        featuredImage.src = '';
        featuredPreview.classList.add('d-none');
        removeFeatured.classList.add('d-none');
    });
})();
</script>
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\content_picker') ?>
<?php endif ?>
<?= $this->endSection() ?>
