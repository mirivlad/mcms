<?php
$interactiveServices = service('mcmsModuleServices');
$showRatings = empty($isPreview) && $interactiveServices->has('ratings.repository');
$showComments = empty($isPreview) && $interactiveServices->has('comments.repository');
?>
<?php if ($showRatings || $showComments): ?>
<div class="mcms-content-interactions">
    <?php if ($showRatings): ?>
        <?= view('MCMS\\Modules\\Ratings\\Views\\widget', ['targetType' => $targetType, 'targetId' => (string) $targetId]) ?>
    <?php endif ?>
    <?php if ($showComments): ?>
        <?= view('MCMS\\Modules\\Comments\\Views\\widget', ['targetType' => $targetType, 'targetId' => (string) $targetId]) ?>
    <?php endif ?>
</div>
<?php endif ?>
