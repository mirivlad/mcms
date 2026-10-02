<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($metaTitle ?? $siteName) ?></title>
<?php if (! empty($metaDescription)): ?>
<meta name="description" content="<?= esc((string) $metaDescription, 'attr') ?>">
<?php endif ?>
<link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\public_assets') ?>
<?php endif ?>
    <?php if (! empty($metaKeywords)): ?><meta name="keywords" content="<?= esc((string) $metaKeywords, 'attr') ?>"><?php endif ?>
    <?php if (! empty($isPreview)): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif ?>
</head>
<body><main class="container py-5">
<article class="mx-auto" style="max-width:900px">
<a href="<?= site_url('news') ?>">&larr; <?= esc(lang('News.Public.backToNews')) ?></a>
<h1 class="my-4"><?= esc($article['title']) ?></h1>
<?php if (($article['featured_image_url'] ?? null) !== null): ?>
<img class="img-fluid rounded mb-4" src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt="">
<?php endif ?>
<div class="text-secondary mb-4"><?= esc(sprintf(lang('News.Public.published'), $article['published_at'])) ?></div>
<div class="mcms-page-content"><?= $article['body'] ?></div>
</article>
</main></body></html>
