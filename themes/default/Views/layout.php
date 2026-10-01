<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->renderSection('title') ?></title>
    <?= $this->renderSection('meta') ?>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= esc($themeAssets . 'css/theme.css', 'attr') ?>">
</head>
<body class="mcms-site">
<a class="visually-hidden-focusable mcms-skip-link" href="#main-content"><?= esc(lang('DefaultTheme.Accessibility.skipToContent')) ?></a>

<header class="mcms-site-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg px-0" aria-label="<?= esc(lang('DefaultTheme.Accessibility.primaryNavigation'), 'attr') ?>">
            <a class="navbar-brand fw-semibold" href="<?= site_url('/') ?>"><?= esc($siteName) ?></a>
        </nav>
    </div>
</header>

<main id="main-content">
    <?= $this->renderSection('content') ?>
</main>

<footer class="mcms-site-footer mt-auto">
    <div class="container py-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div class="fw-medium"><?= esc($siteName) ?></div>
        <div class="text-secondary small"><?= esc(lang('DefaultTheme.Footer.poweredBy')) ?></div>
    </div>
</footer>

<script src="<?= site_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>