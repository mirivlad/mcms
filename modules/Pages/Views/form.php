<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
    $isEdit = $page !== null;
    $sanitizer = new \MCMS\Core\Content\HtmlSanitizer();
    $editorBody = $sanitizer->sanitize((string) old('body', $page['body'] ?? ''));
    $statusValue = (string) old('status', $page['status'] ?? $defaultStatus);
    $seoTitle = (string) old('seo_title', $page['seo_title'] ?? '');
    $seoDescription = (string) old('seo_description', $page['seo_description'] ?? '');
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
    overscroll-behavior-x: contain;
    scrollbar-width: thin;
    border-bottom: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface-secondary);
}
.mcms-editor-toolbar .btn {
    flex: 0 0 2.25rem;
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
}
.mcms-editor-toolbar [data-html-source-toggle] {
    width: auto;
    flex: 0 0 auto;
    padding: 0 .65rem;
    margin-left: auto;
}
.mcms-editor-source {
    min-height: 24rem;
    resize: vertical;
    tab-size: 4;
}
.mcms-editor-toolbar .mcms-editor-toolbar-separator {
    flex: 0 0 1px;
    width: 1px;
    align-self: stretch;
    margin: 0 .25rem;
    background: var(--tblr-border-color);
}
.mcms-editor {
    min-height: 34rem;
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
.mcms-editor h2,
.mcms-editor h3 {
    margin-top: 1.5rem;
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
    border-left: 1px solid var(--tblr-border-color) !important;
}
.mcms-permalink-group .form-control {
    min-width: 0;
}
.mcms-permalink-group:focus-within {
    border-color: var(--tblr-primary);
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
#media-dialog {
    width: min(1000px, 94vw);
    max-height: 88vh;
}
#media-dialog::backdrop {
    background: rgba(0, 0, 0, .45);
}
</style>

<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('Pages.Admin.title')) ?></div>
            <h2 class="page-title"><?= esc(lang($isEdit ? 'Pages.Admin.edit' : 'Pages.Admin.create')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-outline-secondary d-none d-md-inline-flex" href="<?= site_url('admin/pages') ?>">
                <i class="ti ti-arrow-left me-1"></i><?= esc(lang('MCMS.common.back')) ?>
            </a>
            <a class="d-inline-flex d-md-none align-items-center gap-1 text-secondary text-decoration-none small" href="<?= site_url('admin/pages') ?>">
                <i class="ti ti-arrow-left"></i><?= esc(lang('MCMS.common.back')) ?>
            </a>
        </div>
    </div>
</div>

<form method="post" id="page-form">
    <input type="hidden" name="content_id" value="<?= (int) ($page['id'] ?? 0) ?>">
    <?= csrf_field() ?>
    <?= view('admin/content/request') ?>
    <?= view('admin/content/workflow', ['workflowUrl' => site_url('admin/pages'), 'contentId' => (int) ($page['id'] ?? 0), 'editorId' => 'page-editor']) ?>
    <?= view('admin/content/preview', ['previewUrl' => site_url('admin/pages/preview'), 'editorId' => 'page-editor']) ?>

    <div class="row row-cards">
        <div class="col-xl-9">
            <div class="card mb-3">
                <div class="card-body">
                    <input
                        class="form-control form-control-lg mb-3"
                        id="page_title"
                        name="title"
                        maxlength="255"
                        required
                        placeholder="<?= esc(lang('MCMS.common.title'), 'attr') ?>"
                        value="<?= esc(old('title', $page['title'] ?? '')) ?>"
                    >

                    <label class="form-label" for="page_slug"><?= esc(lang('Pages.Admin.permalink')) ?></label>
                    <div class="input-group mcms-permalink-group">
                        <span class="input-group-text">
                            <i class="ti ti-link"></i><span class="ms-2">/</span>
                        </span>
                        <input
                            class="form-control font-monospace"
                            id="page_slug"
                            name="slug"
                            maxlength="190"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            value="<?= esc(old('slug', $page['slug'] ?? '')) ?>"
                        >
                    </div>
                    <div class="form-hint"><?= esc(lang('Pages.Admin.slugHint')) ?></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= esc(lang('Pages.Admin.body')) ?></label>

                <div class="mcms-editor-shell">
                    <div class="mcms-editor-toolbar" id="editor-toolbar">
                        <button class="btn btn-ghost-secondary" type="button" data-command="undo" title="<?= esc(lang('MCMS.content.undo'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.content.undo'), 'attr') ?>"><i class="ti ti-arrow-back-up"></i></button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="redo" title="<?= esc(lang('MCMS.content.redo'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.content.redo'), 'attr') ?>"><i class="ti ti-arrow-forward-up"></i></button>
                        <span class="mcms-editor-toolbar-separator"></span>
                        <button class="btn btn-ghost-secondary" type="button" data-command="bold" title="<?= esc(lang('Pages.Admin.bold'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.bold'), 'attr') ?>">
                            <i class="ti ti-bold"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="italic" title="<?= esc(lang('Pages.Admin.italic'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.italic'), 'attr') ?>">
                            <i class="ti ti-italic"></i>
                        </button>

                        <span class="mcms-editor-toolbar-separator"></span>

                        <button class="btn btn-ghost-secondary" type="button" data-format="h2" title="<?= esc(lang('Pages.Admin.heading'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.heading'), 'attr') ?>">
                            <i class="ti ti-heading"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-format="p" title="<?= esc(lang('Pages.Admin.paragraph'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.paragraph'), 'attr') ?>">
                            <i class="ti ti-letter-case"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="insertUnorderedList" title="<?= esc(lang('Pages.Admin.bulletList'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.bulletList'), 'attr') ?>">
                            <i class="ti ti-list"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="insertOrderedList" title="<?= esc(lang('Pages.Admin.numberedList'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.numberedList'), 'attr') ?>">
                            <i class="ti ti-list-numbers"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-format="blockquote" title="<?= esc(lang('Pages.Admin.quote'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.quote'), 'attr') ?>">
                            <i class="ti ti-blockquote"></i>
                        </button>

                        <span class="mcms-editor-toolbar-separator"></span>

                        <button class="btn btn-ghost-secondary" type="button" id="editor-link" title="<?= esc(lang('Pages.Admin.link'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.link'), 'attr') ?>">
                            <i class="ti ti-link"></i>
                        </button>
                        <button class="btn btn-ghost-secondary" type="button" data-command="removeFormat" title="<?= esc(lang('Pages.Admin.clearFormatting'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.clearFormatting'), 'attr') ?>">
                            <i class="ti ti-clear-formatting"></i>
                        </button>

                        <span class="mcms-editor-toolbar-separator"></span>

                        <button class="btn btn-ghost-primary" type="button" id="open-media" title="<?= esc(lang('Pages.Admin.mediaPicker'), 'attr') ?>" aria-label="<?= esc(lang('Pages.Admin.mediaPicker'), 'attr') ?>">
                            <i class="ti ti-photo-plus"></i>
                        </button>
                        <?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
                        <button class="btn btn-ghost-primary" type="button" data-gallery-picker data-editor="page-editor" aria-haspopup="dialog" aria-controls="content-gallery-dialog" title="<?= esc(lang('Galleries.Admin.insertGallery'), 'attr') ?>" aria-label="<?= esc(lang('Galleries.Admin.insertGallery'), 'attr') ?>">
                            <i class="ti ti-photo"></i><span class="ms-1"><?= esc(lang('Galleries.Admin.galleryButton')) ?></span>
                        </button>
                        <?php endif ?>
                        <button class="btn btn-ghost-primary" type="button" data-insert-table title="<?= esc(lang('MCMS.content.insertTable'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.content.insertTable'), 'attr') ?>"><i class="ti ti-table"></i></button>
                        <button type="button" class="btn btn-ghost-primary" data-html-source-toggle aria-pressed="false" aria-controls="page-editor page_body" title="<?= esc(lang('MCMS.content.htmlSource'), 'attr') ?>" aria-label="<?= esc(lang('MCMS.content.htmlSource'), 'attr') ?>"><i class="ti ti-code" aria-hidden="true"></i><span class="ms-1">HTML</span></button>
                    </div>

                    <div
                        id="page-editor"
                        class="mcms-editor"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                    ><?= $editorBody ?></div>
                </div>

                <?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?><p class="form-hint mt-2"><?= esc(lang('Pages.Admin.galleryHint')) ?></p><?php endif ?>
                <textarea id="page_body" name="body" class="d-none form-control font-monospace mcms-editor-source" rows="20" spellcheck="false" aria-label="<?= esc(lang('MCMS.content.htmlSource'), 'attr') ?>"><?= esc($editorBody) ?></textarea>
                <p class="form-hint mt-2" data-html-source-hint hidden><?= esc(lang('MCMS.content.htmlSourceHint')) ?></p>
            </div>

            <div class="card mb-3"><div class="card-body">
                <label class="form-label" for="content-tags"><?= esc(lang('MCMS.content.tags')) ?></label>
                <input class="form-control" id="content-tags" name="tags" maxlength="1000" value="<?= esc(old('tags', $page['tags'] ?? '')) ?>">
                <p class="form-hint mb-0"><?= esc(lang('MCMS.content.tagsHint')) ?></p>
            </div></div>
            <details class="card" <?= $seoOpen ? 'open' : '' ?>>
                <summary class="card-header mcms-seo-summary">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="ti ti-world-search me-2"></i><?= esc(lang('Pages.Admin.seo')) ?>
                        </h3>
                        <div class="text-secondary small mt-1"><?= esc(lang('Pages.Admin.seoHint')) ?></div>
                    </div>
                    <i class="ti ti-chevron-down mcms-seo-chevron text-secondary" aria-hidden="true"></i>
                </summary>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="seo_title"><?= esc(lang('Pages.Admin.seoTitle')) ?></label>
                        <input
                            class="form-control"
                            id="seo_title"
                            name="seo_title"
                            maxlength="255"
                            value="<?= esc($seoTitle) ?>"
                        >
                    </div>
                    <div>
                        <label class="form-label" for="seo_description"><?= esc(lang('Pages.Admin.seoDescription')) ?></label>
                        <textarea
                            class="form-control"
                            id="seo_description"
                            name="seo_description"
                            maxlength="320"
                            rows="3"
                        ><?= esc($seoDescription) ?></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="content-keywords"><?= esc(lang('MCMS.content.keywords')) ?></label>
                        <input class="form-control" id="content-keywords" name="seo_keywords" maxlength="1000" value="<?= esc(old('seo_keywords', $page['seo_keywords'] ?? '')) ?>">
                        <p class="form-hint mb-0"><?= esc(lang('MCMS.content.seoOverride')) ?></p>
                    </div>
                </div>
            </details>
        </div>

        <div class="col-xl-3">
            <div class="position-sticky" style="top:1rem">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="ti ti-send me-2"></i><?= esc(lang('MCMS.common.status')) ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <select class="form-select" id="page_status" name="status">
                                <option value="draft" <?= $statusValue === 'draft' ? 'selected' : '' ?>>
                                    <?= esc(lang('Pages.Admin.statusDraft')) ?>
                                </option>
                                <option value="published" <?= $statusValue === 'published' ? 'selected' : '' ?>>
                                    <?= esc(lang('Pages.Admin.statusPublished')) ?>
                                </option>
                            </select>
                        </div>

                        <?php if ($isEdit && $page['status'] === 'published'): ?>
                            <div class="mb-3">
                                <a
                                    class="btn btn-outline-secondary w-100 text-wrap"
                                    href="<?= esc(site_url(ltrim($page['url'], '/'))) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="ti ti-external-link me-1"></i><?= esc(lang('Pages.Admin.viewPublic')) ?>
                                </a>
                            </div>
                        <?php endif ?>

                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ti ti-device-floppy me-1"></i>
                            <?= esc(lang($isEdit ? 'Pages.Admin.saveSubmit' : 'Pages.Admin.createSubmit')) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<dialog id="media-dialog" class="border-0 rounded shadow-lg p-0">
    <div class="card mb-0">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ti ti-photo me-2"></i><?= esc(lang('Pages.Admin.mediaPicker')) ?>
            </h3>
            <button class="btn btn-icon btn-ghost-secondary ms-auto" type="button" id="close-media" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <div class="card-body">
            <div class="input-group mb-3">
                <span class="input-group-text"><i class="ti ti-search"></i></span>
                <input
                    class="form-control"
                    id="media-search"
                    type="search"
                    placeholder="<?= esc(lang('Pages.Admin.mediaSearch'), 'attr') ?>"
                >
                <button class="btn btn-outline-primary" id="media-search-button" type="button">
                    <?= esc(lang('MCMS.common.search')) ?>
                </button>
            </div>

            <div id="media-loading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="text-secondary"><?= esc(lang('Pages.Admin.mediaLoading')) ?></div>
            </div>

            <div id="media-error" class="alert alert-danger d-none">
                <div class="d-flex align-items-center gap-2">
                    <i class="ti ti-alert-circle"></i>
                    <span class="flex-fill"><?= esc(lang('Pages.Admin.mediaLoadError')) ?></span>
                    <button class="btn btn-outline-danger btn-sm" type="button" id="media-retry">
                        <i class="ti ti-refresh me-1"></i><?= esc(lang('Pages.Admin.retry')) ?>
                    </button>
                </div>
            </div>

            <div id="media-results" class="row row-cards"></div>

            <div id="media-empty" class="empty d-none">
                <div class="empty-img">
                    <i class="ti ti-photo-off" style="font-size:4rem"></i>
                </div>
                <p class="empty-title"><?= esc(lang('Pages.Admin.mediaEmpty')) ?></p>
            </div>
        </div>
    </div>
</dialog>

<?= view('admin/content/html_source') ?>
<script>
(() => {
    const form = document.getElementById('page-form');
    const editor = document.getElementById('page-editor');
    const body = document.getElementById('page_body');
    const toolbar = document.getElementById('editor-toolbar');
    const dialog = document.getElementById('media-dialog');
    const results = document.getElementById('media-results');
    const empty = document.getElementById('media-empty');
    const loading = document.getElementById('media-loading');
    const error = document.getElementById('media-error');
    const mediaSearch = document.getElementById('media-search');
    const mediaEndpoint = <?= json_encode(site_url('admin/pages/media'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const insertLabel = <?= json_encode(lang('Pages.Admin.insertImage'), JSON_UNESCAPED_UNICODE) ?>;
    let savedRange = null;

    const htmlEditor = window.mcmsCreateHtmlSourceEditor(editor, body, toolbar);
    const syncBody = htmlEditor.sync;
    const visualChanged = htmlEditor.changed;

    const rememberSelection = () => {
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };

    const restoreSelection = () => {
        editor.focus();
        if (!savedRange || !editor.contains(savedRange.commonAncestorContainer)) return;
        const selection = window.getSelection();
        if (!selection) return;
        selection.removeAllRanges();
        selection.addRange(savedRange);
    };

    editor.addEventListener('keyup', rememberSelection);
    editor.addEventListener('mouseup', rememberSelection);
    toolbar.addEventListener('mousedown', rememberSelection);

    document.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            htmlEditor.format(button.dataset.command);
            rememberSelection();
        });
    });

    document.querySelectorAll('[data-format]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            htmlEditor.formatBlock(button.dataset.format);
            rememberSelection();
        });
    });

    document.getElementById('editor-link').addEventListener('click', () => {
        restoreSelection();
        const url = window.prompt('URL');
        if (url) {
            htmlEditor.link(url);
        }
    });

    const insertImage = (item) => {
        restoreSelection();
        htmlEditor.insertImage(item);
        dialog.close();
        editor.focus();
    };

    const renderMedia = (items) => {
        results.replaceChildren();

        for (const item of items) {
            const col = document.createElement('div');
            col.className = 'col-sm-6 col-lg-4';

            const card = document.createElement('div');
            card.className = 'card h-100';

            const preview = document.createElement('div');
            preview.className = 'card-img-top d-flex align-items-center justify-content-center bg-body-tertiary overflow-hidden';
            preview.style.height = '180px';

            const image = document.createElement('img');
            image.src = item.thumbnail_url || item.url;
            image.alt = item.alt_text || '';
            image.className = 'w-100 h-100';
            image.style.objectFit = 'contain';
            image.loading = 'lazy';

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            const name = document.createElement('div');
            name.className = 'text-truncate fw-bold mb-2';
            name.textContent = item.title || item.original_name || '';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-primary btn-sm w-100';
            button.innerHTML = '<i class="ti ti-photo-plus me-1"></i>' + insertLabel;
            button.addEventListener('click', () => insertImage(item));

            preview.appendChild(image);
            cardBody.append(name, button);
            card.append(preview, cardBody);
            col.appendChild(card);
            results.appendChild(col);
        }
    };

    const loadMedia = async () => {
        results.replaceChildren();
        empty.classList.add('d-none');
        error.classList.add('d-none');
        loading.classList.remove('d-none');

        const url = new URL(mediaEndpoint, window.location.origin);
        const query = mediaSearch.value.trim();
        if (query) {
            url.searchParams.set('q', query);
        }

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (! response.ok) {
                throw new Error('media request failed');
            }

            const payload = await response.json();
            const items = Array.isArray(payload.items) ? payload.items : [];

            loading.classList.add('d-none');

            if (items.length === 0) {
                empty.classList.remove('d-none');
                return;
            }

            renderMedia(items);
        } catch (_) {
            loading.classList.add('d-none');
            error.classList.remove('d-none');
        }
    };

    document.getElementById('open-media').addEventListener('click', async () => {
        rememberSelection();
        dialog.showModal();
        await loadMedia();
    });

    document.getElementById('close-media').addEventListener('click', () => dialog.close());
    document.getElementById('media-search-button').addEventListener('click', loadMedia);
    document.getElementById('media-retry').addEventListener('click', loadMedia);

    mediaSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadMedia();
        }
    });

    form.addEventListener('submit', syncBody);
})();
</script>
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\content_picker') ?>
<?php endif ?>
<?= $this->endSection() ?>