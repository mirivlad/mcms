<?php
$ratingRepository = service('mcmsModuleServices')->get('ratings.repository');
$ratingTarget = $ratingRepository->publicTarget((string) $targetType, (string) $targetId);
?>
<?php if ($ratingTarget !== null): ?>
<?php
$ratingSummary = $ratingRepository->summary((string) $targetType, (string) $targetId);
session();
$ratingUser = auth()->loggedIn() ? (int) auth()->id() : null;
$ratingGuestAllowed = (bool) setting('ratings.allow_guests');
$myRating = $ratingRepository->userRating((string) $targetType, (string) $targetId, $ratingUser, session_id());
$ratingChallenge = null;
$ratingCaptchaUnavailable = false;
$ratingCaptcha = service('mcmsModuleServices')->get('captcha.verifier');
$ratingCaptchaRequired = ($ratingUser !== null || $ratingGuestAllowed) && (method_exists($ratingCaptcha, 'required') ? $ratingCaptcha->required('ratings.vote', $ratingUser !== null) : $ratingUser === null);
$ratingPrompt = session()->getFlashdata('captcha_prompt');
$ratingShouldIssue = $ratingCaptchaRequired && is_array($ratingPrompt)
    && ($ratingPrompt['purpose'] ?? '') === 'ratings.vote'
    && ($ratingPrompt['type'] ?? '') === (string) $targetType
    && ($ratingPrompt['id'] ?? '') === (string) $targetId;
if ($ratingShouldIssue) {
    try {
        $ratingChallenge = $ratingCaptcha->issue('ratings.vote', session_id());
    } catch (RuntimeException) {
        $ratingCaptchaUnavailable = true;
    }
}
?>
<section class="mcms-ratings mt-4 mb-4" id="ratings" aria-labelledby="ratings-heading">
    <h2 id="ratings-heading" class="h4"><?= esc(lang('Ratings.Public.title')) ?></h2>
    <?php if ($ratingSummary['count'] > 0): ?>
        <p class="mb-2"><strong><?= number_format((float) $ratingSummary['average'], 1, '.', '') ?> / 5</strong>
        <span class="text-secondary"> (<?= (int) $ratingSummary['count'] ?> <?= esc(lang('Ratings.Public.votes')) ?>)</span></p>
        <div style="max-width:320px" class="mb-3">
        <?php for ($score = 5; $score >= 1; $score--): ?>
            <div class="d-flex align-items-center gap-2">
                <span style="width:36px"><?= $score ?> ★</span>
                <progress value="<?= (int) $ratingSummary['distribution'][$score] ?>"
                    max="<?= max(1, (int) $ratingSummary['count']) ?>" style="flex:1"></progress>
                <span><?= (int) $ratingSummary['distribution'][$score] ?></span>
            </div>
        <?php endfor ?>
        </div>
    <?php endif ?>
    <?php if ($notice = session()->getFlashdata('ratings_notice')): ?>
        <div class="alert alert-success" role="status"><?= esc($notice) ?></div>
    <?php endif ?>
    <?php if ($error = session()->getFlashdata('ratings_error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif ?>
    <?php if ($ratingUser !== null || $ratingGuestAllowed): ?>
        <form method="post" action="<?= site_url('ratings/vote') ?>"
            data-captcha-guard="ratings.vote"
            data-captcha-required="<?= $ratingCaptchaRequired ? '1' : '0' ?>"
            data-captcha-endpoint="<?= esc(site_url('captcha/challenge'), 'attr') ?>"
            data-captcha-csrf-name="<?= esc(csrf_token(), 'attr') ?>"
            data-captcha-confirm="<?= esc(lang('Captcha.Public.confirmRating'), 'attr') ?>"
            data-captcha-error="<?= esc(lang('Captcha.Public.unavailable'), 'attr') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="target_type" value="<?= esc($targetType, 'attr') ?>">
            <input type="hidden" name="target_id" value="<?= esc($targetId, 'attr') ?>">
            <?php if (($pendingStars = (int) session()->getFlashdata('rating_pending_stars')) >= 1 && $pendingStars <= 5): ?>
                <p class="mb-2"><?= esc(lang('Ratings.Public.pendingRating')) ?>: <?= $pendingStars ?> / 5</p>
            <?php endif ?>
            <?php if ($myRating !== null): ?>
                <p class="mb-1"><?= esc(lang('Ratings.Public.myRating')) ?>: <?= (int) $myRating ?> / 5</p>
            <?php endif ?>
            <div data-captcha-slot>
                <?php if ($ratingChallenge !== null): ?>
                    <?= view('MCMS\\Modules\\Captcha\\Views\\widget', ['challenge' => $ratingChallenge]) ?>
                <?php endif ?>
            </div>
            <?php if ($ratingCaptchaUnavailable): ?>
                <div class="alert alert-warning"><?= esc(lang('Ratings.Public.captchaUnavailable')) ?></div>
            <?php endif ?>
            <div class="d-flex gap-1" role="group" aria-label="<?= esc(lang('Ratings.Public.submit'), 'attr') ?>">
                <?php for ($score = 1; $score <= 5; $score++): ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                        name="stars" value="<?= $score ?>"
                        <?= $ratingCaptchaRequired && $ratingChallenge === null && $ratingCaptchaUnavailable ? 'disabled' : '' ?>
                        aria-label="<?= $score ?> / 5" title="<?= $score ?> / 5"
                        style="font-size:1.35rem;color:<?= $myRating !== null && $score <= $myRating ? '#c58b1a' : 'inherit' ?>">
                        ★
                    </button>
                <?php endfor ?>
            </div>
        </form>
    <?php else: ?>
        <p class="text-secondary"><?= esc(lang('Ratings.Public.login')) ?>
            <a href="<?= site_url('login') ?>"><?= esc(lang('MCMS.account.login')) ?></a>
            <?php if ((bool) setting('Auth.allowRegistration')): ?>
                · <a href="<?= site_url('register') ?>"><?= esc(lang('MCMS.account.register')) ?></a>
            <?php endif ?>
        </p>
    <?php endif ?>
</section>
<?php endif ?>
