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
    $publishedInput = $publishedTimestamp === false ? '' : date('Y-m-d\TH:i', $publishedTimestamp);
    $featuredId = (string) old('featured_media_id', $article['featured_media_id'] ?? '');
    $seoTitle = (string) old('seo_title', $article['seo_title'] ?? '');
    $seoDescription = (string) old('seo_description', $article['seo_description'] ?? '');
?>

<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a href="<?= site_url('admin/news') ?>" class="text-secondary text-decoration-none"><?= esc(lang('News.Admin.title')) ?></a>
            </div>
            <h2 class="page-title"><?= esc($isEdit ? lang('News.Admin.edit') : lang('News.Admin.create')) ?></h2>
        </div>
        <?php if ($isEdit && ($article['is_live'] ?? false)): ?>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="<?= esc(site_url(ltrim($article['url'], '/'))) ?>" target="_blank" rel="noopener">
                    <i class="ti ti-external-link me-1"></i><?= esc(lang('News.Admin.viewPublic')) ?>
                </a>
            </div>
        <?php endif ?>
    </div>
</div>

<form method="post" id="news-form">
    <?= csrf_field() ?>
    <div class="row row-cards">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="news-title"><?= esc(lang('MCMS.common.title')) ?></label>
                        <input class="form-control form-control-lg" id="news-title" name="title" maxlength="255" required value="<?= esc($titleValue) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="news-slug"><?= esc(lang('News.Admin.slug')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">/news/</span>
                            <input class="form-control font-monospace" id="news-slug" name="slug" maxlength="190" value="<?= esc($slugValue) ?>">
                        </div>
                        <div class="form-hint"><?= esc(lang('News.Admin.slugHint')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="news-excerpt"><?= esc(lang('News.Admin.excerpt')) ?></label>
                        <textarea class="form-control" id="news-excerpt" name="excerpt" rows="3" maxlength="500"><?= esc($excerptValue) ?></textarea>
                        <div class="form-hint"><?= esc(lang('News.Admin.excerptHint')) ?></div>
                    </div>
                    <div class="mb-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <label class="form-label mb-0 me-auto"><?= esc(lang('News.Admin.body')) ?></label>
                            <div class="btn-list">
                                <button class="btn btn-sm btn-outline-secondary editor-command" type="button" data-command="bold"><i class="ti ti-bold"></i></button>
                                <button class="btn btn-sm btn-outline-secondary editor-command" type="button" data-command="italic"><i class="ti ti-italic"></i></button>
                                <button class="btn btn-sm btn-outline-secondary editor-block" type="button" data-block="h2"><i class="ti ti-h-2"></i></button>
                                <button class="btn btn-sm btn-outline-secondary editor-command" type="button" data-command="insertUnorderedList"><i class="ti ti-list"></i></button>
                                <button class="btn btn-sm btn-outline-secondary editor-command" type="button" data-command="insertOrderedList"><i class="ti ti-list-numbers"></i></button>
                                <button class="btn btn-sm btn-outline-secondary" id="editor-link" type="button"><i class="ti ti-link"></i></button>
                            </div>
                        </div>
                        <div id="news-editor" class="form-control" contenteditable="true" style="min-height:24rem;overflow:auto"><?= $bodyValue ?></div>
                        <textarea class="d-none" id="news-body" name="body"><?= esc($bodyValue) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('News.Admin.seo')) ?></h3></div>
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
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('MCMS.common.status')) ?></h3></div>
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
                            <a href="<?= esc(site_url(ltrim($article['url'], '/'))) ?>" target="_blank" rel="noopener"><?= esc($article['url']) ?></a>
                        </div>
                    <?php endif ?>
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-device-floppy me-1"></i><?= esc(lang($isEdit ? 'News.Admin.saveSubmit' : 'News.Admin.createSubmit')) ?>
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= esc(lang('News.Admin.featuredImage')) ?></h3></div>
                <div class="card-body">
                    <select class="form-select" name="featured_media_id" id="featured-media">
                        <option value=""><?= esc(lang('News.Admin.featuredImageNone')) ?></option>
                        <?php foreach ($mediaImages as $media): ?>
                            <option
                                value="<?= (int) $media['id'] ?>"
                                data-url="<?= esc($media['url'], 'attr') ?>"
                                <?= $featuredId !== '' && (int) $featuredId === (int) $media['id'] ? 'selected' : '' ?>
                            >
                                <?= esc((string) (($media['title'] ?? null) ?: ($media['original_name'] ?? ('#' . $media['id'])))) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-hint mt-2"><?= esc(lang('News.Admin.featuredImageHint')) ?></div>
                    <div id="featured-preview" class="mt-3 d-none">
                        <img id="featured-preview-image" alt="" class="img-fluid rounded border">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(() => {
    const form = document.getElementById('news-form');
    const editor = document.getElementById('news-editor');
    const body = document.getElementById('news-body');

    form.addEventListener('submit', () => {
        body.value = editor.innerHTML;
    });

    document.querySelectorAll('.editor-command').forEach((button) => {
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(button.dataset.command, false);
        });
    });

    document.querySelectorAll('.editor-block').forEach((button) => {
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand('formatBlock', false, button.dataset.block);
        });
    });

    document.getElementById('editor-link').addEventListener('click', () => {
        const url = window.prompt('URL');
        if (!url) return;
        editor.focus();
        document.execCommand('createLink', false, url);
    });

    const select = document.getElementById('featured-media');
    const preview = document.getElementById('featured-preview');
    const image = document.getElementById('featured-preview-image');

    const syncPreview = () => {
        const option = select.selectedOptions[0];
        const url = option?.dataset.url || '';
        if (!url) {
            image.src = '';
            preview.classList.add('d-none');
            return;
        }

        image.src = url;
        preview.classList.remove('d-none');
    };

    select.addEventListener('change', syncPreview);
    syncPreview();
})();
</script>
<?= $this->endSection() ?>
