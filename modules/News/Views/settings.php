<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('NewsSettings.title')) ?></h2>
<div class="card"><div class="card-body">
<form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="default-status"><?= esc(lang('NewsSettings.defaultStatus')) ?></label>
        <select class="form-select" id="default-status" name="default_status">
            <?php foreach (['draft', 'published'] as $status): ?><option value="<?= $status ?>" <?= old('default_status', $defaultStatus) === $status ? 'selected' : '' ?>><?= esc(lang('NewsSettings.status.' . $status)) ?></option><?php endforeach ?>
        </select>
        <div class="form-hint"><?= esc(lang('NewsSettings.defaultHint')) ?></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="per-page"><?= esc(lang('NewsSettings.perPage')) ?></label>
        <input class="form-control" id="per-page" name="per_page" type="number" min="1" max="50" required value="<?= esc((string) old('per_page', $perPage), 'attr') ?>">
        <div class="form-hint"><?= esc(lang('NewsSettings.pageHint')) ?></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
</form>
</div></div>

<?= $this->endSection() ?>
