<?= $this->extend('MCMS\\Themes\\Signature\\Views\\layout') ?>
<?= $this->section('content') ?>
<div class="sig-container sig-contact-shell"><div class="sig-contact-intro"><p class="sig-eyebrow"><?= esc($siteName) ?></p><h1><?= esc(lang('Feedback.Public.title')) ?><span class="sig-heading-dot" aria-hidden="true">.</span></h1><p class="sig-lead"><?= esc(lang('Signature.contactLead')) ?></p><span class="sig-contact-decoration" aria-hidden="true">↗</span></div><div class="sig-contact-form"><?= view('MCMS\\Modules\\Feedback\\Views\\_form', ['showTitle' => false], ['saveData' => false]) ?></div></div>
<?= $this->endSection() ?>
