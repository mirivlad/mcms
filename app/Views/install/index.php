<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install mCMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="mb-4">
                        <div class="text-secondary text-uppercase small fw-semibold">mCMS Core</div>
                        <h1 class="h2 mb-2">Initial setup</h1>
                        <p class="text-secondary mb-0">
                            Create the first super administrator and basic site settings.
                        </p>
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
                            <label class="form-label" for="site_name">Site name</label>
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
                            <label class="form-label" for="email">Administrator email</label>
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
                            <label class="form-label" for="username">Administrator username</label>
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
                            <label class="form-label" for="password">Password</label>
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
                            <label class="form-label" for="password_confirm">Confirm password</label>
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
                            <span class="form-check-label">Allow public user registration</span>
                        </label>

                        <button class="btn btn-primary w-100" type="submit">
                            Install mCMS
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
