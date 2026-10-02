<?php
$headerMenu = $themeMenus['header.primary'] ?? [];
$footerPrimary = $themeMenus['footer.primary'] ?? [];
$footerLegal = $themeMenus['footer.legal'] ?? [];
if ($headerMenu === []) {
    $headerMenu[] = ['label' => lang('Signature.home'), 'url' => '/'];
    foreach (['news.archive', 'galleries.archive', 'feedback.contact'] as $provider) {
        $resource = service('mcmsNavigation')->resolve($provider, 'index');
        if ($resource !== null) { $headerMenu[] = $resource; }
    }
}
$version = rawurlencode($theme->version);
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>" data-theme="mcms-signature">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f6f1">
    <title><?= esc($metaTitle ?? $siteName) ?></title>
    <?php if (! empty($metaDescription)): ?><meta name="description" content="<?= esc((string) $metaDescription, 'attr') ?>"><?php endif ?>
    <link rel="icon" href="<?= esc($themeAssets . 'mark.svg', 'attr') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= esc($themeAssets . 'css/theme.css?v=' . $version, 'attr') ?>">
    <script src="<?= esc($themeAssets . 'js/theme.js?v=' . $version, 'attr') ?>" defer></script>
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\public_assets') ?>
<?php endif ?>
</head>
<body>
<a class="sig-skip" href="#main-content"><?= esc(lang('Signature.skip')) ?></a>
<header class="sig-header"><div class="sig-container sig-header-inner">
    <a class="sig-brand" href="<?= site_url('/') ?>"><span class="sig-mark" aria-hidden="true">m<span>·</span></span><span><?= esc($siteName) ?></span></a>
    <nav class="sig-desktop-nav" aria-label="<?= esc(lang('Signature.navigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Signature\\Views\\_navigation', ['items' => $headerMenu]) ?></ul></nav>
    <details class="sig-mobile-nav"><summary><?= esc(lang('Signature.menu')) ?><span aria-hidden="true">☰</span></summary><nav aria-label="<?= esc(lang('Signature.navigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Signature\\Views\\_navigation', ['items' => $headerMenu]) ?></ul></nav></details>
</div></header>
<main id="main-content" tabindex="-1"><?= $this->renderSection('content') ?></main>
<footer class="sig-footer"><div class="sig-container">
    <div class="sig-footer-top"><div><a class="sig-brand" href="<?= site_url('/') ?>"><span class="sig-mark" aria-hidden="true">m<span>·</span></span><span><?= esc($siteName) ?></span></a><p><?= esc(lang('Signature.footer')) ?></p></div>
    <?php if ($footerPrimary !== []): ?><nav aria-label="<?= esc(lang('MCMS.themes.footerNavigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Signature\\Views\\_navigation', ['items' => $footerPrimary]) ?></ul></nav><?php endif ?>
    </div>
    <div class="sig-footer-bottom"><span>© <?= date('Y') ?> <?= esc($siteName) ?></span><span class="sig-credit">mCMS <span aria-hidden="true">/</span> Signature</span>
        <?php if ($footerLegal !== []): ?><ul><?= view('MCMS\\Themes\\Signature\\Views\\_navigation', ['items' => $footerLegal]) ?></ul><?php endif ?>
    </div>
</div></footer>
<dialog class="sig-lightbox" id="sig-lightbox" aria-labelledby="sig-lightbox-title" data-loading="<?= esc(lang('Signature.loading'), 'attr') ?>" data-error="<?= esc(lang('Signature.imageError'), 'attr') ?>">
    <div class="sig-lightbox-toolbar"><h2 id="sig-lightbox-title"><?= esc(lang('Signature.viewer')) ?></h2><button class="sig-icon-button" type="button" data-lightbox-close aria-label="<?= esc(lang('Signature.close'), 'attr') ?>" autofocus>×</button></div>
    <p class="sig-lightbox-status" role="status"></p><img class="sig-lightbox-image" alt=""><p class="sig-lightbox-caption"></p>
    <div class="sig-lightbox-controls"><button class="sig-icon-button" type="button" data-lightbox-prev aria-label="<?= esc(lang('Signature.previous'), 'attr') ?>">←</button><span class="sig-lightbox-count" aria-live="polite"></span><button class="sig-icon-button" type="button" data-lightbox-next aria-label="<?= esc(lang('Signature.next'), 'attr') ?>">→</button><a class="sig-lightbox-original" href="#"><?= esc(lang('Signature.original')) ?> ↗</a></div>
</dialog>
</body>
</html>
