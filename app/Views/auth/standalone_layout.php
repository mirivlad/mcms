<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($metaTitle ?? ($siteName ?? 'mCMS')) ?></title>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= site_url('assets/css/account-nav.css?v=' . \MCMS\Core\Version::VERSION) ?>">
</head>
<body>
    <header class="container py-3">
        <a href="<?= site_url('/') ?>"><?= esc($siteName ?? 'mCMS') ?></a>
        <?= view('auth/nav') ?>
    </header>
    <main><?= $this->renderSection('content') ?></main>
</body>
</html>
