<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="page-pretitle"><?= esc(lang('Pages.Admin.title')) ?></div>
            <h2 class="page-title"><?= esc(lang('Pages.Admin.settings')) ?></h2>
        </div>
        <div class="col-12 col-md-auto ms-md-auto">
            <a class="btn btn-link" href="<?= site_url('admin/pages') ?>"><?= esc(lang('MCMS.common.back')) ?></a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="default_status"><?= esc(lang('Pages.Admin.defaultStatus')) ?></label>
                <select class="form-select" id="default_status" name="default_status">
                    <option value="draft" <?= old('default_status', $defaultStatus) === 'draft' ? 'selected' : '' ?>>
                        <?= esc(lang('Pages.Admin.statusDraft')) ?>
                    </option>
                    <option value="published" <?= old('default_status', $defaultStatus) === 'published' ? 'selected' : '' ?>>
                        <?= esc(lang('Pages.Admin.statusPublished')) ?>
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="home_page_id"><?= esc(lang('Pages.Admin.homePage')) ?></label>
                <select class="form-select" id="home_page_id" name="home_page_id">
                    <option value="0"><?= esc(lang('Pages.Admin.defaultHome')) ?></option>
                    <?php foreach ($homePages as $candidate): ?>
                        <option value="<?= (int) $candidate['id'] ?>" <?= (int) old('home_page_id', $homePageId) === $candidate['id'] ? 'selected' : '' ?>><?= esc($candidate['title']) ?></option>
                    <?php endforeach ?>
                </select>
                <div class="form-hint"><?= esc(lang('Pages.Admin.homeHint')) ?></div>
            </div>
            <button class="btn btn-primary" type="submit"><?= esc(lang('Pages.Admin.saveSettings')) ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
