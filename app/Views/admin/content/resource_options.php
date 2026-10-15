<?php $resourceFields = service('mcmsResourceEditor')->fields(); ?>
<?php if ($resourceFields !== []): ?>
<div class="card my-3"><div class="card-body">
    <h3 class="card-title"><?= esc(lang('MCMS.resourceOptions.title')) ?></h3>
    <div class="row g-3">
    <?php foreach ($resourceFields as $resourceKey => $resourceField): ?>
        <?php $resourceMode = old('resource_options.' . $resourceKey, $resourceId === '' ? 'inherit' : $resourceField->value($resourceType, $resourceId)); ?>
        <div class="col-sm-6"><label class="form-label" for="resource-option-<?= esc($resourceKey, 'attr') ?>"><?= esc($resourceField->label()) ?></label>
            <select class="form-select" id="resource-option-<?= esc($resourceKey, 'attr') ?>" name="resource_options[<?= esc($resourceKey, 'attr') ?>]">
                <?php foreach (['inherit', 'allow', 'deny'] as $mode): ?><option value="<?= $mode ?>" <?= $resourceMode === $mode ? 'selected' : '' ?>><?= esc(lang('MCMS.resourceOptions.' . $mode)) ?></option><?php endforeach ?>
            </select>
        </div>
    <?php endforeach ?>
    </div>
</div></div>
<?php endif ?>
