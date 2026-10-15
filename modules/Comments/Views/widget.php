<?php
$commentServices = service('mcmsModuleServices');
$commentRepository = $commentServices->get('comments.repository');
$commentTarget = $commentRepository->publicTarget((string) $targetType, (string) $targetId);
?>
<?php if ($commentTarget !== null): ?>
<?php
$commentRows = $commentRepository->published((string) $targetType, (string) $targetId);
$commentUser = auth()->loggedIn();
$guestAllowed = (bool) setting('comments.allow_guests');
$commentChallenge = null;
$commentChallengeError = false;
$commentCaptcha = $commentServices->get('captcha.verifier');
$commentCaptchaRequired = ($commentUser || $guestAllowed) && (method_exists($commentCaptcha, 'required') ? $commentCaptcha->required('comments.create', $commentUser) : ! $commentUser);
if ($commentCaptchaRequired) {
    session();
    try {
        $commentChallenge = $commentServices->get('captcha.verifier')->issue('comments.create', session_id());
    } catch (RuntimeException) {
        $commentChallengeError = true;
    }
}
?>
<section class="mcms-comments mt-5" id="comments" aria-labelledby="comments-heading">
    <h2 id="comments-heading" class="mb-4"><?= esc(lang('Comments.Public.title')) ?></h2>
    <?php if ($notice = session()->getFlashdata('comments_notice')): ?>
        <div class="alert alert-success" role="status"><?= esc($notice) ?></div>
    <?php endif ?>
    <?php if ($error = session()->getFlashdata('comments_error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
    <?php endif ?>
    <?php if ($commentRows === []): ?><p class="text-secondary"><?= esc(lang('Comments.Public.empty')) ?></p><?php endif ?>
    <?php foreach ($commentRows as $comment): ?>
        <article class="card mb-2 mcms-comment" id="comment-<?= (int) $comment['id'] ?>" style="--comment-depth:<?= (int) $comment['display_depth'] ?>">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <strong><?= esc($comment['status'] === 'deleted' ? '[deleted]' : $comment['author_name']) ?></strong>
                    <small class="text-secondary"><?= esc($comment['created_at']) ?></small>
                </div>
                <?php if ((int) $comment['display_depth'] > 0): ?><a class="small d-block mb-2" href="#comment-<?= (int) $comment['parent_id'] ?>"><?= esc(lang('Comments.Public.inReplyTo', [(int) $comment['parent_id']])) ?></a><?php endif ?>
                <p class="mb-1" style="white-space:pre-wrap;overflow-wrap:anywhere"><?= esc($comment['body']) ?></p>
                <?php if ($comment['status'] === 'published'): ?>
                <?php if (($commentUser || $guestAllowed) && (int) $comment['depth'] < (int) setting('comments.max_depth')): ?>
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-comment-reply="<?= (int) $comment['id'] ?>"><?= esc(lang('Comments.Public.reply')) ?></button>
                <?php endif ?>
                <details class="small mt-2">
                    <summary><?= esc(lang('Comments.Public.report')) ?></summary>
                    <form method="post" action="<?= site_url('comments/report') ?>" class="mt-2 d-flex gap-2 flex-wrap">
                        <?= csrf_field() ?>
                        <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                        <input type="hidden" name="target_type" value="<?= esc($targetType, 'attr') ?>">
                        <input type="hidden" name="target_id" value="<?= esc($targetId, 'attr') ?>">
                        <input class="form-control form-control-sm" name="reason" maxlength="250" required placeholder="<?= esc(lang('Comments.Public.reason'), 'attr') ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= esc(lang('Comments.Public.report')) ?></button>
                    </form>
                </details>
                <?php endif ?>
            </div>
        </article>
    <?php endforeach ?>
    <?php if ($commentUser || $guestAllowed): ?>
    <div class="card mt-4" id="comment-form">
        <div class="card-body">
            <?php if ($commentChallengeError): ?>
                <p class="alert alert-warning"><?= esc(lang('Comments.Public.captchaUnavailable')) ?></p>
            <?php endif ?>
            <form method="post" action="<?= site_url('comments/create') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="target_type" value="<?= esc($targetType, 'attr') ?>">
                <input type="hidden" name="target_id" value="<?= esc($targetId, 'attr') ?>">
                <div hidden aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <?php if (! $commentUser): ?>
                    <div class="mb-3"><label class="form-label" for="comment-name"><?= esc(lang('Comments.Public.name')) ?></label><input class="form-control" id="comment-name" name="name" maxlength="120" value="<?= esc(old('name') ?? '', 'attr') ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="comment-email"><?= esc(lang('Comments.Public.email')) ?></label><input class="form-control" type="email" id="comment-email" name="email" maxlength="254" value="<?= esc(old('email') ?? '', 'attr') ?>"></div>
                <?php endif ?>
                <?php if ($commentRows !== []): ?>
                    <div class="mb-3">
                        <label class="form-label" for="comment-parent"><?= esc(lang('Comments.Public.reply')) ?></label>
                        <select class="form-select" id="comment-parent" name="parent_id">
                            <option value="">—</option>
                            <?php foreach ($commentRows as $parent): ?>
                                <?php if ($parent['status'] === 'published' && (int) $parent['depth'] < (int) setting('comments.max_depth')): ?>
                                <option value="<?= (int) $parent['id'] ?>">#<?= (int) $parent['id'] ?> — <?= esc($parent['author_name']) ?></option>
                                <?php endif ?>
                            <?php endforeach ?>
                        </select>
                    </div>
                <?php endif ?>
                <div class="mb-3"><label class="form-label" for="comment-body"><?= esc(lang('Comments.Public.body')) ?></label><textarea class="form-control" id="comment-body" name="body" minlength="2" maxlength="5000" rows="4" required><?= esc(old('body') ?? '') ?></textarea></div>
                <?php if ($commentChallenge !== null): ?>
                    <?= view('MCMS\\Modules\\Captcha\\Views\\widget', ['challenge' => $commentChallenge]) ?>
                <?php endif ?>
                <button class="btn btn-primary" type="submit" <?= $commentCaptchaRequired && $commentChallenge === null ? 'disabled' : '' ?>><?= esc(lang('Comments.Public.submit')) ?></button>
            </form>
        </div>
    </div>
    <?php else: ?>
        <p class="text-secondary"><?= esc(lang('Comments.Public.login')) ?>
            <a href="<?= site_url('login') ?>"><?= esc(lang('MCMS.account.login')) ?></a>
            <?php if ((bool) setting('Auth.allowRegistration')): ?>
                · <a href="<?= site_url('register') ?>"><?= esc(lang('MCMS.account.register')) ?></a>
            <?php endif ?>
        </p>
    <?php endif ?>
<script>
document.querySelectorAll('[data-comment-reply]').forEach(button => button.addEventListener('click', () => {
    const parent = document.getElementById('comment-parent');
    if (parent) parent.value = button.dataset.commentReply;
    document.getElementById('comment-body')?.focus();
}));
</script>
</section>
<?php endif ?>
