<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
    $isEdit = $page !== null;
    $sanitizer = new \MCMS\Modules\Pages\Services\HtmlSanitizer();
    $editorBody = $sanitizer->sanitize((string) old('body', $page['body'] ?? ''));
    $statusValue = (string) old('status', $page['status'] ?? $defaultStatus);
?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('Pages.Admin.title')) ?></div>
            <h2 class="page-title"><?= esc(lang($isEdit ? 'Pages.Admin.edit' : 'Pages.Admin.create')) ?></h2>
        </div>
        <div class="col-auto ms-auto">
            <a class="btn btn-link" href="<?= site_url('admin/pages') ?>"><?= esc(lang('MCMS.common.back')) ?></a>
        </div>
    </div>
</div>

<form method="post" id="page-form">
    <?= csrf_field() ?>

    <div class="row row-cards">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="page_title"><?= esc(lang('MCMS.common.title')) ?></label>
                        <input
                            class="form-control"
                            id="page_title"
                            name="title"
                            maxlength="255"
                            required
                            value="<?= esc(old('title', $page['title'] ?? '')) ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="page_slug"><?= esc(lang('Pages.Admin.slug')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">/</span>
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

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Pages.Admin.body')) ?></label>

                        <div class="btn-list mb-2" id="editor-toolbar">
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-command="bold">
                                <?= esc(lang('Pages.Admin.bold')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-command="italic">
                                <?= esc(lang('Pages.Admin.italic')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-format="h2">
                                <?= esc(lang('Pages.Admin.heading')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-format="p">
                                <?= esc(lang('Pages.Admin.paragraph')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-command="insertUnorderedList">
                                <?= esc(lang('Pages.Admin.bulletList')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-command="insertOrderedList">
                                <?= esc(lang('Pages.Admin.numberedList')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-format="blockquote">
                                <?= esc(lang('Pages.Admin.quote')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="editor-link">
                                <?= esc(lang('Pages.Admin.link')) ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-command="removeFormat">
                                <?= esc(lang('Pages.Admin.clearFormatting')) ?>
                            </button>
                            <button class="btn btn-outline-primary btn-sm" type="button" id="open-media">
                                <?= esc(lang('Pages.Admin.mediaPicker')) ?>
                            </button>
                        </div>

                        <div
                            id="page-editor"
                            class="form-control"
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            style="min-height: 28rem; overflow:auto"
                        ><?= $editorBody ?></div>
                        <textarea id="page_body" name="body" class="d-none"><?= esc($editorBody) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= esc(lang('Pages.Admin.seo')) ?></h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="seo_title"><?= esc(lang('Pages.Admin.seoTitle')) ?></label>
                        <input
                            class="form-control"
                            id="seo_title"
                            name="seo_title"
                            maxlength="255"
                            value="<?= esc(old('seo_title', $page['seo_title'] ?? '')) ?>"
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
                        ><?= esc(old('seo_description', $page['seo_description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="page_status"><?= esc(lang('MCMS.common.status')) ?></label>
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
                            <a class="btn btn-outline-secondary w-100" href="<?= esc(site_url(ltrim($page['url'], '/'))) ?>" target="_blank" rel="noopener">
                                <?= esc(lang('Pages.Admin.viewPublic')) ?>
                            </a>
                        </div>
                    <?php endif ?>

                    <button class="btn btn-primary w-100" type="submit">
                        <?= esc(lang($isEdit ? 'Pages.Admin.saveSubmit' : 'Pages.Admin.createSubmit')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<dialog id="media-dialog" class="border-0 rounded shadow-lg p-0" style="width:min(900px, 94vw)">
    <div class="card mb-0">
        <div class="card-header">
            <h3 class="card-title"><?= esc(lang('Pages.Admin.mediaPicker')) ?></h3>
            <button class="btn-close ms-auto" type="button" id="close-media" aria-label="Close"></button>
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
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
            <div id="media-results" class="row row-cards"></div>
            <div id="media-empty" class="text-secondary d-none"><?= esc(lang('Pages.Admin.mediaEmpty')) ?></div>
        </div>
    </div>
</dialog>

<script>
(() => {
    const form = document.getElementById('page-form');
    const editor = document.getElementById('page-editor');
    const body = document.getElementById('page_body');
    const dialog = document.getElementById('media-dialog');
    const results = document.getElementById('media-results');
    const empty = document.getElementById('media-empty');
    const mediaSearch = document.getElementById('media-search');
    const mediaEndpoint = <?= json_encode(site_url('admin/pages/media'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const insertLabel = <?= json_encode(lang('Pages.Admin.insertImage'), JSON_UNESCAPED_UNICODE) ?>;
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
        if (! savedRange) {
            editor.focus();
            return;
        }

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(savedRange);
    };

    document.getElementById('editor-toolbar').addEventListener('mousedown', rememberSelection);

    document.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(button.dataset.command, false);
            rememberSelection();
            syncBody();
        });
    });

    document.querySelectorAll('[data-format]').forEach((button) => {
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand('formatBlock', false, button.dataset.format);
            rememberSelection();
            syncBody();
        });
    });

    document.getElementById('editor-link').addEventListener('click', () => {
        restoreSelection();
        const url = window.prompt('URL');
        if (url) {
            document.execCommand('createLink', false, url);
            syncBody();
        }
    });

    const insertImage = (item) => {
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

        syncBody();
        dialog.close();
        editor.focus();
    };

    const loadMedia = async () => {
        results.replaceChildren();
        empty.classList.add('d-none');

        const url = new URL(mediaEndpoint, window.location.origin);
        const query = mediaSearch.value.trim();
        if (query) {
            url.searchParams.set('q', query);
        }

        const response = await fetch(url, {
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin',
        });

        if (! response.ok) {
            empty.classList.remove('d-none');
            return;
        }

        const payload = await response.json();
        const items = Array.isArray(payload.items) ? payload.items : [];

        if (items.length === 0) {
            empty.classList.remove('d-none');
            return;
        }

        for (const item of items) {
            const col = document.createElement('div');
            col.className = 'col-sm-6 col-lg-4';

            const card = document.createElement('div');
            card.className = 'card h-100';

            const image = document.createElement('img');
            image.src = item.url;
            image.alt = item.alt_text || '';
            image.className = 'card-img-top';
            image.style.height = '160px';
            image.style.objectFit = 'cover';

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            const name = document.createElement('div');
            name.className = 'text-truncate fw-bold mb-2';
            name.textContent = item.title || item.original_name || '';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary btn-sm w-100';
            button.textContent = insertLabel;
            button.addEventListener('click', () => insertImage(item));

            cardBody.append(name, button);
            card.append(image, cardBody);
            col.appendChild(card);
            results.appendChild(col);
        }
    };

    document.getElementById('open-media').addEventListener('click', async () => {
        rememberSelection();
        dialog.showModal();
        await loadMedia();
    });

    document.getElementById('close-media').addEventListener('click', () => dialog.close());
    document.getElementById('media-search-button').addEventListener('click', loadMedia);
    mediaSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            loadMedia();
        }
    });

    editor.addEventListener('input', syncBody);
    form.addEventListener('submit', syncBody);
})();
</script>
<?= $this->endSection() ?>
