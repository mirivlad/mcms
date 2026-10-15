<?php
$source = (string) ($entry['source'] ?? '');
$visibility = (string) ($entry['visibility'] ?? 'all');
$targetType = (string) ($entry['target_type'] ?? '');
$targetId = (string) ($entry['target_id'] ?? '');
$target = $targetType !== '' && $targetId !== '' ? service('mcmsNavigation')->resolve($targetType, $targetId) : null;
?>
<div class="border rounded p-3 mb-2" data-extra-menu-row>
    <input type="hidden" name="extra_location[]" value="<?= esc($locationId, 'attr') ?>">
    <div class="d-flex gap-2 align-items-center mb-2">
        <span class="small text-secondary flex-fill" data-extra-position></span>
        <div class="d-flex gap-1 flex-shrink-0">
        <button class="btn btn-outline-secondary btn-icon admin-icon-action" type="button" data-extra-up aria-label="<?= esc(lang('MCMS.themes.moveMenuUp'), 'attr') ?>" title="<?= esc(lang('MCMS.themes.moveMenuUp'), 'attr') ?>"><i class="ti ti-arrow-up" aria-hidden="true"></i></button>
        <button class="btn btn-outline-secondary btn-icon admin-icon-action" type="button" data-extra-down aria-label="<?= esc(lang('MCMS.themes.moveMenuDown'), 'attr') ?>" title="<?= esc(lang('MCMS.themes.moveMenuDown'), 'attr') ?>"><i class="ti ti-arrow-down" aria-hidden="true"></i></button>
        <button class="btn btn-outline-danger btn-icon admin-icon-action" type="button" data-extra-remove aria-label="<?= esc(lang('MCMS.themes.removeExtraMenu'), 'attr') ?>" title="<?= esc(lang('MCMS.themes.removeExtraMenu'), 'attr') ?>"><i class="ti ti-x" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="row g-2">
        <div class="col-md-7"><label class="form-label"><?= esc(lang('MCMS.themes.extraMenuSource')) ?>
            <select class="form-select" name="extra_source[]">
                <option value=""><?= esc(lang('MCMS.themes.noMenu')) ?></option>
                <?php foreach ($extraSources as $sourceId => $label): ?><option value="<?= esc($sourceId, 'attr') ?>" <?= $source === $sourceId ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
                <?php if ($source !== '' && ! isset($extraSources[$source])): ?><option value="<?= esc($source, 'attr') ?>" selected><?= esc($source) ?> (<?= esc(lang('MCMS.themes.extraMenuUnavailable')) ?>)</option><?php endif ?>
            </select>
        </label></div>
        <div class="col-md-5"><label class="form-label"><?= esc(lang('MCMS.themes.extraVisibility')) ?>
            <select class="form-select" name="extra_visibility[]" data-extra-visibility>
                <?php foreach (['all','shop','pages','news','products','resource'] as $scope): ?><option value="<?= $scope ?>" <?= $visibility === $scope ? 'selected' : '' ?>><?= esc(lang('MCMS.themes.extraScope.' . $scope)) ?></option><?php endforeach ?>
            </select>
        </label></div>
    </div>
    <div class="mt-2" data-extra-resource <?= $visibility === 'resource' ? '' : 'hidden' ?>>
        <div class="row g-2">
            <div class="col-md-5"><label class="form-label"><?= esc(lang('MCMS.themes.resourceType')) ?>
                <select class="form-select" name="extra_target_type[]" data-extra-provider>
                    <option value="">—</option>
                    <?php foreach ($navigationProviders as $provider): ?><option value="<?= esc($provider['id'], 'attr') ?>" <?= $targetType === $provider['id'] ? 'selected' : '' ?>><?= esc($provider['label']) ?></option><?php endforeach ?>
                    <?php if ($targetType !== '' && ! in_array($targetType, array_column($navigationProviders, 'id'), true)): ?><option value="<?= esc($targetType, 'attr') ?>" selected><?= esc($targetType) ?> (<?= esc(lang('MCMS.themes.extraMenuUnavailable')) ?>)</option><?php endif ?>
                </select>
            </label></div>
            <div class="col-md-7"><label class="form-label"><?= esc(lang('MCMS.themes.resource')) ?>
                <select class="form-select" name="extra_target_id[]" data-extra-target>
                    <option value="">—</option>
                    <?php if ($targetId !== ''): ?><option value="<?= esc($targetId, 'attr') ?>" selected><?= esc($target['label'] ?? ($targetType . ' #' . $targetId . ' (' . lang('MCMS.themes.extraMenuUnavailable') . ')')) ?></option><?php endif ?>
                </select>
            </label></div>
        </div>
        <div class="d-flex gap-2 mt-2"><input class="form-control" type="search" maxlength="100" data-extra-query aria-label="<?= esc(lang('MCMS.common.search'), 'attr') ?>"><button class="btn btn-outline-secondary" type="button" data-extra-search><?= esc(lang('MCMS.common.search')) ?></button></div>
    </div>
</div>
