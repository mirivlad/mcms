<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle"><?= esc(lang('MCMS.common.core')) ?></div>
            <h2 class="page-title"><?= esc(lang('MCMS.settings.title')) ?></h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="site_name"><?= esc(lang('MCMS.settings.siteName')) ?></label>
                <input class="form-control" id="site_name" name="site_name" maxlength="120" required value="<?= esc(old('site_name', (string) $siteName)) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="locale"><?= esc(lang('MCMS.settings.siteLanguage')) ?></label>
                <select class="form-select" id="locale" name="locale" required>
                    <?php foreach ($supportedLocales as $supportedLocale): ?>
                        <option value="<?= esc($supportedLocale) ?>" <?= old('locale', $locale) === $supportedLocale ? 'selected' : '' ?>>
                            <?= esc(lang('MCMS.locale.' . $supportedLocale)) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <div class="form-hint"><?= esc(lang('MCMS.settings.siteLanguageHint')) ?></div>
            </div>

            <label class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="allow_registration" value="1" <?= $allowRegistration ? 'checked' : '' ?>>
                <span class="form-check-label"><?= esc(lang('MCMS.settings.allowRegistration')) ?></span>
            </label>
            <p class="form-hint mb-3"><?= esc(lang('MCMS.account.captchaRegistrationHint')) ?></p>

            <button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.settings.save')) ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
