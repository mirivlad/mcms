<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="container mcms-public-spacing"><?= view('MCMS\\Modules\\Galleries\\Views\\_index') ?></div>
<?= $this->endSection() ?>
