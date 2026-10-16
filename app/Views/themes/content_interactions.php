<?php
$interactiveServices = service('mcmsModuleServices');
$showRatings = empty($isPreview) && $interactiveServices->has('ratings.repository');
$showComments = empty($isPreview) && $interactiveServices->has('comments.repository');
?>
<?php if ($showRatings || $showComments): ?>
<script src="<?= esc(site_url('captcha/assets/guard.js') . '?v=' . substr(hash_file('sha256', ROOTPATH . 'modules/Captcha/assets/guard.js'), 0, 12), 'attr') ?>" defer></script>
<div class="mcms-content-interactions">
    <?php if ($showRatings): ?>
        <?= view('MCMS\\Modules\\Ratings\\Views\\widget', ['targetType' => $targetType, 'targetId' => (string) $targetId]) ?>
    <?php endif ?>
    <?php if ($showComments): ?>
        <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => $targetType, 'targetId' => (string) $targetId]) ?>
    <?php endif ?>
</div>
<?php endif ?>
