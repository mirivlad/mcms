<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="container"><?= view('MCMS\\Modules\\Catalogue\\Views\\_index') ?></div>
<?= $this->endSection() ?>
