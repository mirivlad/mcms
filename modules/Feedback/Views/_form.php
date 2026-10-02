<h1 class="mb-4"><?= esc(lang('Feedback.Public.title')) ?></h1>
<?php if ($sent): ?><div class="alert alert-success" role="status"><?= esc(lang('Feedback.Public.sent')) ?></div><?php endif ?>
<?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><?= esc($error) ?></div><?php endif ?>
<form method="post" action="<?= site_url('contact') ?>" class="mx-auto" style="max-width:720px">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="contact-name"><?= esc(lang('Feedback.Public.name')) ?></label><input class="form-control" id="contact-name" name="name" maxlength="120" autocomplete="name" value="<?= esc($values['name'] ?? '', 'attr') ?>" required></div>
    <div class="mb-3"><label class="form-label" for="contact-email"><?= esc(lang('Feedback.Public.email')) ?></label><input class="form-control" id="contact-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= esc($values['email'] ?? '', 'attr') ?>" required></div>
    <div class="mb-3"><label class="form-label" for="contact-message"><?= esc(lang('Feedback.Public.message')) ?></label><textarea class="form-control" id="contact-message" name="message" rows="7" maxlength="5000" required><?= esc($values['message'] ?? '') ?></textarea></div>
    <div hidden aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
    <button class="btn btn-primary" type="submit"><?= esc(lang('Feedback.Public.send')) ?></button>
</form>
