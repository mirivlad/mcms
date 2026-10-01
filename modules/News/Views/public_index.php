<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc(lang('News.Public.title')) ?> · <?= esc($siteName) ?></title>
<link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body><main class="container py-5">
<h1 class="mb-4"><?= esc(lang('News.Public.title')) ?></h1>
<?php if ($articles === []): ?><p class="text-secondary"><?= esc(lang('News.Public.empty')) ?></p><?php endif ?>
<div class="row g-4">
<?php foreach ($articles as $article): ?>
<div class="col-md-6"><article class="card h-100">
<?php if (($article['featured_image_url'] ?? null) !== null): ?>
<img class="card-img-top" style="aspect-ratio:16/9;object-fit:cover" src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt="">
<?php endif ?>
<div class="card-body">
<div class="text-secondary small mb-2"><?= esc((string) $article['published_at']) ?></div>
<h2 class="h3"><a href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>"><?= esc($article['title']) ?></a></h2>
<?php if (($article['excerpt'] ?? null) !== null): ?><p><?= esc($article['excerpt']) ?></p><?php endif ?>
</div></article></div>
<?php endforeach ?>
</div>
</main></body></html>
