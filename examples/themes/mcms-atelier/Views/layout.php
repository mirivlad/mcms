<?php
$headerMenu = $themeMenus['header.primary'] ?? [];
$footerPrimary = $themeMenus['footer.primary'] ?? [];
$footerLegal = $themeMenus['footer.legal'] ?? [];
if ($headerMenu === []) {
    $headerMenu[] = ['label' => \MCMS\Themes\Atelier\Views\Text::get('home'), 'url' => '/'];
    foreach (['news.archive', 'galleries.archive', 'catalogue.archive', 'feedback.contact'] as $provider) {
        $resource = service('mcmsNavigation')->resolve($provider, 'index');
        if ($resource !== null) { $headerMenu[] = $resource; }
    }
}
if (! in_array('/search/results', array_column($headerMenu, 'url'), true)) {
    $headerMenu[] = ['label' => lang('MCMS.search.title'), 'url' => '/search/results'];
}
$version = rawurlencode($theme->version);
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>" data-theme="<?= esc($theme->id, 'attr') ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#233b35">
    <title><?= esc($metaTitle ?? $siteName) ?></title>
    <?php if (! empty($metaDescription)): ?><meta name="description" content="<?= esc((string) $metaDescription, 'attr') ?>"><?php endif ?>
    <?php if (empty($themeAppearance['favicon'])): ?><link rel="icon" href="<?= esc($themeAssets . 'mark.svg', 'attr') ?>" type="image/svg+xml"><?php endif ?>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= esc($themeAssets . 'css/theme.css?v=' . $version, 'attr') ?>">
    <script src="<?= esc($themeAssets . 'js/theme.js?v=' . $version, 'attr') ?>" defer></script>
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\public_assets') ?>
<?php endif ?>
    <?php if (! empty($metaKeywords)): ?><meta name="keywords" content="<?= esc((string) $metaKeywords, 'attr') ?>"><?php endif ?>
    <?php if (! empty($metaRobots)): ?><meta name="robots" content="<?= esc($metaRobots, 'attr') ?>"><?php endif ?>
    <?php if (! empty($isPreview)): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif ?>
    <?= view('themes/appearance_head') ?>
</head>
<body class="<?= ! empty($isThemeHome) ? 'site-home' : 'site-inner' ?>">
<a class="site-skip" href="#main-content"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('skip')) ?></a>
<div class="site-container atelier-masthead">
    <a class="site-brand" href="<?= site_url('/') ?>"><?php if (! empty($themeAppearance['logo'])): ?><img class="mcms-brand-logo" src="<?= esc(site_url(ltrim($themeAppearance['logo']['url'], '/')), 'attr') ?>" alt=""><?php endif ?><span><?= esc($siteName) ?></span></a>
    <p><?= nl2br(esc(\MCMS\Themes\Atelier\Views\Text::get('tagline'))) ?></p>
</div>
<header class="site-header"><div class="site-container site-header-inner">

    <nav class="site-desktop-nav" aria-label="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('navigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Atelier\\Views\\_navigation', ['items' => $headerMenu]) ?></ul></nav>
    <details class="site-mobile-nav"><summary><?= esc(\MCMS\Themes\Atelier\Views\Text::get('menu')) ?><span aria-hidden="true">☰</span></summary><nav aria-label="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('navigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Atelier\\Views\\_navigation', ['items' => $headerMenu]) ?></ul></nav></details>
</div></header>
<main id="main-content" tabindex="-1"><?= $this->renderSection('content') ?></main>
<footer class="site-footer"><div class="site-container">
    <div class="site-footer-top"><div><a class="site-brand" href="<?= site_url('/') ?>"><?php if (! empty($themeAppearance['logo'])): ?><img class="mcms-brand-logo" src="<?= esc(site_url(ltrim($themeAppearance['logo']['url'], '/')), 'attr') ?>" alt=""><?php endif ?><span><?= esc($siteName) ?></span></a><p><?= esc(\MCMS\Themes\Atelier\Views\Text::get('footer')) ?></p></div>
    <?php if ($footerPrimary !== []): ?><nav aria-label="<?= esc(lang('MCMS.themes.footerNavigation'), 'attr') ?>"><ul><?= view('MCMS\\Themes\\Atelier\\Views\\_navigation', ['items' => $footerPrimary]) ?></ul></nav><?php endif ?>
    </div>
    <?= view('themes/contacts') ?>
    <div class="site-footer-bottom"><span>© <?= date('Y') ?> <?= esc($siteName) ?></span><span class="site-credit">mCMS <span aria-hidden="true">/</span> Atelier</span>
        <?php if ($footerLegal !== []): ?><ul><?= view('MCMS\\Themes\\Atelier\\Views\\_navigation', ['items' => $footerLegal]) ?></ul><?php endif ?>
    </div>
</div></footer>
<dialog class="site-lightbox" id="site-lightbox" aria-labelledby="site-lightbox-title" data-loading="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('loading'), 'attr') ?>" data-error="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('imageError'), 'attr') ?>">
    <div class="site-lightbox-toolbar"><h2 id="site-lightbox-title"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('viewer')) ?></h2><button class="site-icon-button" type="button" data-lightbox-close aria-label="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('close'), 'attr') ?>" autofocus>×</button></div>
    <p class="site-lightbox-status" role="status"></p><img class="site-lightbox-image" alt=""><p class="site-lightbox-caption"></p>
    <div class="site-lightbox-controls"><button class="site-icon-button" type="button" data-lightbox-prev aria-label="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('previous'), 'attr') ?>">←</button><span class="site-lightbox-count" aria-live="polite"></span><button class="site-icon-button" type="button" data-lightbox-next aria-label="<?= esc(\MCMS\Themes\Atelier\Views\Text::get('next'), 'attr') ?>">→</button><a class="site-lightbox-original" href="#"><?= esc(\MCMS\Themes\Atelier\Views\Text::get('original')) ?> ↗</a></div>
</dialog>
</body>
</html>
