<?= $this->extend('MCMS\\Themes\\Focus\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="site-container site-contact-shell"><div class="site-contact-intro"><p class="site-eyebrow"><?= esc($siteName) ?></p><h1><?= esc(lang('Feedback.Public.title')) ?><span class="site-heading-dot" aria-hidden="true">.</span></h1><p class="site-lead"><?= esc(lang('Focus.contactLead')) ?></p><span class="site-contact-decoration" aria-hidden="true">↗</span></div><div class="site-contact-form"><?= view('MCMS\\Modules\\Feedback\\Views\\_form', ['showTitle' => false], ['saveData' => false]) ?></div></div>
<?= $this->endSection() ?>
