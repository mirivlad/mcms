<?php
$headerMenu = $themeMenus['header.primary'] ?? [];
$footerPrimary = $themeMenus['footer.primary'] ?? [];
$footerLegal = $themeMenus['footer.legal'] ?? [];

$menuUrl = static function (?string $url): ?string {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    if (str_starts_with($url, '/')) {
        return site_url(ltrim($url, '/'));
    }

    return $url;
};
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>" data-theme="mcms-default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($metaTitle ?? $siteName) ?></title>
    <?php if (! empty($metaDescription)): ?>
        <meta name="description" content="<?= esc((string) $metaDescription, 'attr') ?>">
    <?php endif ?>
    <link rel="stylesheet" href="<?= site_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= esc($themeAssets . 'css/theme.css?v=' . rawurlencode($theme?->version ?? '0'), 'attr') ?>">
<?php if (service('mcmsModuleServices')->has('galleries.shortcode')): ?>
<?= view('MCMS\\Modules\\Galleries\\Views\\public_assets') ?>
<?php endif ?>
    <?php if (! empty($metaKeywords)): ?><meta name="keywords" content="<?= esc((string) $metaKeywords, 'attr') ?>"><?php endif ?>
    <?php if (! empty($isPreview)): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif ?>
</head>
<body>
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="<?= site_url('/') ?>"><?= esc($siteName) ?></a>

            <?php if ($headerMenu !== []): ?>
                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#site-navigation"
                    aria-controls="site-navigation"
                    aria-expanded="false"
                    aria-label="<?= esc(lang('MCMS.themes.openNavigation'), 'attr') ?>"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="site-navigation">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        <?php foreach ($headerMenu as $item): ?>
                            <?php
                                $children = $item['children'] ?? [];
                                $url = $menuUrl($item['url'] ?? null);
                                $newWindow = ! empty($item['open_in_new']);
                                $target = $newWindow ? ' target="_blank" rel="noopener noreferrer"' : '';
                            ?>
                            <?php if ($children !== []): ?>
                                <li class="nav-item dropdown">
                                    <a
                                        class="nav-link dropdown-toggle"
                                        href="#"
                                        role="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                    ><?= esc($item['label']) ?></a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($url !== null): ?>
                                            <li>
                                                <a class="dropdown-item fw-semibold" href="<?= esc($url, 'attr') ?>"<?= $target ?>>
                                                    <?= esc($item['label']) ?>
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif ?>
                                        <?php foreach ($children as $child): ?>
                                            <?php
                                                $childUrl = $menuUrl($child['url'] ?? null);
                                                $childNewWindow = ! empty($child['open_in_new']);
                                            ?>
                                            <li>
                                                <?php if ($childUrl !== null): ?>
                                                    <a
                                                        class="dropdown-item"
                                                        href="<?= esc($childUrl, 'attr') ?>"
                                                        <?= $childNewWindow ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                                    ><?= esc($child['label']) ?></a>
                                                <?php else: ?>
                                                    <span class="dropdown-item-text"><?= esc($child['label']) ?></span>
                                                <?php endif ?>
                                            </li>
                                        <?php endforeach ?>
                                    </ul>
                                </li>
                            <?php elseif ($url !== null): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= esc($url, 'attr') ?>"<?= $target ?>><?= esc($item['label']) ?></a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <span class="nav-link disabled"><?= esc($item['label']) ?></span>
                                </li>
                            <?php endif ?>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif ?>
        </div>
    </nav>
</header>

<main>
    <?= $this->renderSection('content') ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-main">
            <a class="footer-brand" href="<?= site_url('/') ?>"><?= esc($siteName) ?></a>

            <?php if ($footerPrimary !== []): ?>
                <nav aria-label="<?= esc(lang('MCMS.themes.footerNavigation'), 'attr') ?>">
                    <ul class="footer-links">
                        <?php foreach ($footerPrimary as $item): ?>
                            <?php $url = $menuUrl($item['url'] ?? null); ?>
                            <li>
                                <?php if ($url !== null): ?>
                                    <a
                                        href="<?= esc($url, 'attr') ?>"
                                        <?= ! empty($item['open_in_new']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                    ><?= esc($item['label']) ?></a>
                                <?php else: ?>
                                    <span><?= esc($item['label']) ?></span>
                                <?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </nav>
            <?php endif ?>
        </div>

        <div class="footer-meta">
            <span>&copy; <?= date('Y') ?> <?= esc($siteName) ?></span>
            <?php if ($footerLegal !== []): ?>
                <ul class="footer-legal">
                    <?php foreach ($footerLegal as $item): ?>
                        <?php $url = $menuUrl($item['url'] ?? null); ?>
                        <li>
                            <?php if ($url !== null): ?>
                                <a
                                    href="<?= esc($url, 'attr') ?>"
                                    <?= ! empty($item['open_in_new']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                ><?= esc($item['label']) ?></a>
                            <?php else: ?>
                                <span><?= esc($item['label']) ?></span>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
</footer>

<script src="<?= site_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
