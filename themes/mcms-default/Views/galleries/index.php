<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="container py-5"><?= view('MCMS\\Modules\\Galleries\\Views\\_index') ?></div>
<?= $this->endSection() ?>
