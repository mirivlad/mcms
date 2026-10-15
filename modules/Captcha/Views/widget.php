<?php $captchaId = 'captcha-' . substr($challenge['token'], 0, 12); ?>
<fieldset class="mb-3 mcms-captcha" style="min-width:0;padding:1rem;border:1px solid var(--bs-border-color,#888);border-radius:.375rem;color:inherit">
    <legend class="float-none w-auto px-1 fs-6"><?= esc(lang('Captcha.Public.verification')) ?></legend>
    <?php if (isset($challenge['text_question'])): ?>
        <p id="<?= $captchaId ?>-question"><?= esc($challenge['text_question']) ?></p>
        <details class="mb-3">
            <summary><?= esc(lang('Captcha.Public.visualAlternative')) ?></summary>
            <p class="mt-2"><?= esc($challenge['question']) ?></p>
            <img src="<?= esc($challenge['image_url'], 'attr') ?>" width="340" height="158" alt="<?= esc($challenge['question'], 'attr') ?>" loading="lazy" style="max-width:100%;height:auto">
        </details>
    <?php else: ?>
        <p id="<?= $captchaId ?>-question"><?= esc($challenge['question']) ?></p>
        <img class="mb-2" src="<?= esc($challenge['image_url'], 'attr') ?>" width="340" height="158" alt="<?= esc($challenge['question'], 'attr') ?>" style="max-width:100%;height:auto">
    <?php endif ?>
    <input type="hidden" name="captcha_token" value="<?= esc($challenge['token'], 'attr') ?>">
    <label class="form-label" for="<?= $captchaId ?>-answer"><?= esc(lang('Captcha.Public.answer')) ?></label>
    <input class="form-control" id="<?= $captchaId ?>-answer" name="captcha_answer" type="text" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" required aria-describedby="<?= $captchaId ?>-question <?= $captchaId ?>-hint" style="max-width:12rem">
    <p class="form-hint mt-2 mb-0" id="<?= $captchaId ?>-hint"><?= esc(lang('Captcha.Public.hint', [(int) ceil($challenge['expires_in'] / 60)])) ?></p>
</fieldset>
