<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc((string) (($article['seo_title'] ?? null) ?: $article['title'])) ?> · <?= esc($siteName) ?></title>
<?php if (($article['seo_description'] ?? $article['excerpt'] ?? null) !== null): ?>
<meta name="description" content="<?= esc((string) ($article['seo_description'] ?? $article['excerpt']), 'attr') ?>">
<?php endif ?>
<link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
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
