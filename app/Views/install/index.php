<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(lang('MCMS.install.title')) ?></title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="mb-4">
                        <div class="text-secondary text-uppercase small fw-semibold">mCMS Core</div>
                        <h1 class="h2 mb-2"><?= esc(lang('MCMS.install.heading')) ?></h1>
                        <p class="text-secondary mb-0"><?= esc(lang('MCMS.install.intro')) ?></p>
                    </div>

                    <?php $errors = session()->getFlashdata('errors') ?? []; ?>
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= esc($error) ?></li>
                                <?php endforeach ?>
                            </ul>
                        </div>
                    <?php endif ?>

                    <form method="post" action="<?= site_url('install') ?>">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="site_name"><?= esc(lang('MCMS.install.siteName')) ?></label>
                            <input
                                class="form-control"
                                id="site_name"
                                name="site_name"
                                maxlength="120"
                                required
                                value="<?= esc(old('site_name', 'mCMS')) ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="locale"><?= esc(lang('MCMS.install.language')) ?></label>
                            <select class="form-select" id="locale" name="locale" required>
                                <?php foreach (config('App')->supportedLocales as $locale): ?>
                                    <option value="<?= esc($locale) ?>" <?= old('locale', service('request')->getLocale()) === $locale ? 'selected' : '' ?>>
                                        <?= esc(lang('MCMS.locale.' . $locale)) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email"><?= esc(lang('MCMS.install.email')) ?></label>
                            <input
                                class="form-control"
                                id="email"
                                type="email"
                                name="email"
                                autocomplete="email"
                                required
                                value="<?= esc(old('email')) ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="username"><?= esc(lang('MCMS.install.username')) ?></label>
                            <input
                                class="form-control"
                                id="username"
                                name="username"
                                minlength="3"
                                maxlength="30"
                                autocomplete="username"
                                required
                                value="<?= esc(old('username', 'admin')) ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password"><?= esc(lang('MCMS.install.password')) ?></label>
                            <input
                                class="form-control"
                                id="password"
                                type="password"
                                name="password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password_confirm"><?= esc(lang('MCMS.install.passwordConfirm')) ?></label>
                            <input
                                class="form-control"
                                id="password_confirm"
                                type="password"
                                name="password_confirm"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <label class="form-check form-switch mb-4">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="allow_registration"
                                value="1"
                                <?= old('allow_registration', '1') === '1' ? 'checked' : '' ?>
                            >
                            <span class="form-check-label"><?= esc(lang('MCMS.install.allowRegistration')) ?></span>
                        </label>

                        <button class="btn btn-primary w-100" type="submit">
                            <?= esc(lang('MCMS.install.submit')) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="<?= site_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
