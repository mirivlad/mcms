<?php
$labels = [];
foreach (['main', 'makeMain', 'crop', 'replace', 'remove', 'removeConfirm', 'up', 'down', 'saving', 'saved', 'uploading', 'failed', 'busy', 'empty', 'drag', 'originalSize', 'selectedSize', 'invalidFile'] as $key) {
    $labels[$key] = lang('ProductImages.' . $key);
}
$config = ['base' => site_url('admin/product-images/' . $setId), 'items' => $items, 'labels' => $labels, 'csrfName' => csrf_token()];
$assets = dirname(__DIR__) . '/assets/';
?>
<link rel="stylesheet" href="<?= site_url('admin/product-images/assets/editor.css') ?>?v=<?= substr(hash_file('sha256', $assets . 'editor.css'), 0, 12) ?>">
<section class="card mb-4" id="product-images-editor" data-config="<?= esc(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'attr') ?>">
    <div class="card-header"><h2 class="card-title"><?= esc(lang('ProductImages.title')) ?></h2><span class="ms-auto text-muted" data-image-count></span></div>
    <div class="card-body">
        <p class="text-muted"><?= esc(lang('ProductImages.hint')) ?></p>
        <div class="product-upload" data-drop-zone>
            <i class="ti ti-photo-plus fs-1" aria-hidden="true"></i>
            <strong><?= esc(lang('ProductImages.drop')) ?></strong>
            <button type="button" class="btn btn-primary" data-choose><?= esc(lang('ProductImages.choose')) ?></button>
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-files>
            <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-replacement>
        </div>
        <div class="product-upload-progress" data-progress aria-live="polite"></div>
        <p class="text-muted small my-3"><?= esc(lang('ProductImages.immediate')) ?></p>
        <p data-image-status role="status" class="small"></p>
        <div class="product-image-grid" data-image-list role="list"></div>
    </div>
    <dialog class="product-crop-dialog" data-crop-dialog aria-labelledby="product-crop-title">
        <div class="d-flex justify-content-between align-items-center mb-3"><h3 id="product-crop-title" class="mb-0"><?= esc(lang('ProductImages.crop')) ?></h3><button type="button" class="btn-close" data-crop-cancel aria-label="<?= esc(lang('ProductImages.cancel'), 'attr') ?>"></button></div>
        <p class="text-muted small"><?= esc(lang('ProductImages.cropHint')) ?></p>
        <div class="product-crop-stage"><canvas data-crop-canvas aria-label="<?= esc(lang('ProductImages.crop'), 'attr') ?>"></canvas></div>
        <div class="d-flex gap-2 flex-wrap my-3"><button type="button" class="btn btn-sm" data-crop-reset><?= esc(lang('ProductImages.reset')) ?></button><button type="button" class="btn btn-sm" data-crop-square><?= esc(lang('ProductImages.square')) ?></button><span class="ms-auto text-muted small" data-crop-dimensions></span></div>
        <label class="form-label" for="product-output-size"><?= esc(lang('ProductImages.size')) ?></label>
        <input class="form-control mb-3" id="product-output-size" data-crop-size type="number" min="300" max="2400" step="1" value="1600">
        <details class="mb-3"><summary><?= esc(lang('ProductImages.crop')) ?></summary><div class="row g-2 mt-1">
            <?php foreach (['x', 'y', 'width', 'height'] as $key): ?><div class="col-6 col-sm-3"><label class="form-label" for="crop-<?= $key ?>"><?= esc(lang('ProductImages.' . $key)) ?></label><input class="form-control" type="number" id="crop-<?= $key ?>" data-crop-field="<?= $key ?>" min="<?= in_array($key, ['x', 'y'], true) ? 0 : 16 ?>" step="1"></div><?php endforeach ?>
        </div></details>
        <p class="text-danger small" role="alert" data-crop-error></p>
        <div class="d-flex justify-content-end gap-2"><button type="button" class="btn" data-crop-cancel><?= esc(lang('ProductImages.cancel')) ?></button><button type="button" class="btn btn-primary" data-crop-apply><?= esc(lang('ProductImages.apply')) ?></button></div>
    </dialog>
</section>
<script src="<?= site_url('admin/product-images/assets/editor.js') ?>?v=<?= substr(hash_file('sha256', $assets . 'editor.js'), 0, 12) ?>" defer></script>
