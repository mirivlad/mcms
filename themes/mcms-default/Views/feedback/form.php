<?= $this->extend('MCMS\\Themes\\DefaultTheme\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="container mcms-public-spacing"><?= view('MCMS\\Modules\\Feedback\\Views\\_form') ?></div>
<?= $this->endSection() ?>
