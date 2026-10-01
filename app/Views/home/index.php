<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>mCMS</title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="p-5 bg-body-tertiary rounded-3">
                <h1 class="display-5 fw-bold">mCMS</h1>
                <p class="lead"><?= esc(lang('MCMS.home.lead')) ?></p>
                <p class="mb-0"><?= esc(lang('MCMS.home.description')) ?></p>
            </div>
        </div>
    </div>
</main>
<script src="<?= site_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
