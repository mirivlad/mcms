<div class="mb-3 mcms-captcha">
    <label class="form-label" for="captcha-answer"><?= esc($challenge['question']) ?></label>
    <div class="mb-2">
        <img src="<?= esc($challenge['image_url'], 'attr') ?>" width="340" height="158"
             alt="<?= esc($challenge['question'], 'attr') ?>" loading="eager">
    </div>
    <input type="hidden" name="captcha_token" value="<?= esc($challenge['token'], 'attr') ?>">
    <input class="form-control" id="captcha-answer" name="captcha_answer" type="number"
           min="0" max="15" inputmode="numeric" autocomplete="off" required
           aria-label="<?= esc(lang('Captcha.Public.answer'), 'attr') ?>" style="max-width:12rem">
</div>
