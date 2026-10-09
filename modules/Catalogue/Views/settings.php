<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('CatalogueSettings.title')) ?></h2>
<div class="card"><div class="card-body">
<form method="post">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label" for="per-page"><?= esc(lang('CatalogueSettings.perPage')) ?></label>
        <input class="form-control" id="per-page" name="per_page" type="number" min="1" max="100" required value="<?= esc((string) old('per_page', $perPage), 'attr') ?>">
        <div class="form-hint"><?= esc(lang('CatalogueSettings.pageHint')) ?></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
</form>
</div></div>

<?= $this->endSection() ?>
