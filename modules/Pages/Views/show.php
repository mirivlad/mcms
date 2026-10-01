<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc((string) (($page['seo_title'] ?? null) ?: $page['title'])) ?> · <?= esc($siteName) ?></title>
    <?php if (($page['seo_description'] ?? null) !== null): ?>
        <meta name="description" content="<?= esc($page['seo_description'], 'attr') ?>">
    <?php endif ?>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body>
<main class="container py-5">
    <article class="mx-auto" style="max-width: 900px">
        <h1 class="mb-4"><?= esc($page['title']) ?></h1>
        <div class="mcms-page-content">
            <?= $page['body'] ?>
        </div>
    </article>
</main>
<script src="<?= site_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
