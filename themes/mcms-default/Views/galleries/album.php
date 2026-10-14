<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="container mcms-public-spacing"><?= view('themes/content_actions', ['targetType' => 'galleries.album', 'targetId' => (string) $album['id']]) ?><?= view('MCMS\\Modules\\Galleries\\Views\\_album') ?></div>
<?= $this->endSection() ?>
