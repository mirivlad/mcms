<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>

<?= $this->section('content') ?>
<div class="container news-shell">
    <header class="news-heading">
        <div class="hero-kicker"><?= esc($siteName) ?></div>
        <h1><?= esc(lang('News.Public.title')) ?></h1>
    </header>

    <?php if ($articles === []): ?>
        <div class="content-card news-empty"><?= esc(lang('News.Public.empty')) ?></div>
    <?php else: ?>
        <div class="news-grid">
            <?php foreach ($articles as $article): ?>
                <article class="content-card news-card">
                    <?php if (($article['featured_image_url'] ?? null) !== null): ?>
                        <a href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>" class="news-card-image">
                            <img src="<?= esc(site_url(ltrim($article['featured_image_url'], '/')), 'attr') ?>" alt="">
                        </a>
                    <?php endif ?>
                    <div class="news-card-body">
                        <time class="news-date" datetime="<?= esc((string) $article['published_at'], 'attr') ?>">
                            <?= esc(date('d.m.Y H:i', strtotime((string) $article['published_at']))) ?>
                        </time>
                        <h2><a href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>"><?= esc($article['title']) ?></a></h2>
                        <?php if (($article['excerpt'] ?? null) !== null): ?>
                            <p><?= esc($article['excerpt']) ?></p>
                        <?php endif ?>
                        <a class="news-read-more" href="<?= esc(site_url(ltrim($article['url'], '/')), 'attr') ?>">
                            <?= esc(lang('News.Public.readMore')) ?> →
                        </a>
                    </div>
                </article>
            <?php endforeach ?>
        </div>

        <?php if ($lastPage > 1): ?>
            <nav class="news-pagination" aria-label="<?= esc(lang('News.Public.title'), 'attr') ?>">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline-secondary" href="<?= site_url('news') ?>?page=<?= $page - 1 ?>">← <?= esc(lang('News.Public.newer')) ?></a>
                <?php endif ?>
                <span class="text-secondary"><?= $page ?> / <?= $lastPage ?></span>
                <?php if ($page < $lastPage): ?>
                    <a class="btn btn-outline-secondary" href="<?= site_url('news') ?>?page=<?= $page + 1 ?>"><?= esc(lang('News.Public.older')) ?> →</a>
                <?php endif ?>
            </nav>
        <?php endif ?>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
