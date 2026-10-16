<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $oldPolicies = (array) old('policies', $options['policies']); ?>
<h2 class="page-title mb-3"><?= esc(lang('Captcha.Admin.name')) ?></h2>
<div class="card mb-3"><div class="card-body">
<form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="captcha-difficulty"><?= esc(lang('Captcha.Settings.difficulty')) ?></label><select class="form-select" id="captcha-difficulty" name="difficulty">
            <?php foreach (['easy', 'standard', 'hard'] as $level): ?><option value="<?= $level ?>" <?= old('difficulty', $options['difficulty']) === $level ? 'selected' : '' ?>><?= esc(lang('Captcha.Settings.' . $level)) ?></option><?php endforeach ?>
        </select><div class="form-hint mt-2"><?= esc(lang('Captcha.Settings.difficultyHelp')) ?></div></div>
        <?php foreach (['ttl' => [120,900], 'session_limit' => [4,120], 'ip_limit' => [8,600], 'verify_limit' => [8,600]] as $key => [$min,$max]): ?>
        <div class="col-md-6"><label class="form-label" for="captcha-<?= $key ?>"><?= esc(lang('Captcha.Settings.' . $key)) ?></label><input class="form-control" id="captcha-<?= $key ?>" name="<?= $key ?>" type="number" min="<?= $min ?>" max="<?= $max ?>" required value="<?= esc((string) old($key, $options[$key]), 'attr') ?>"></div>
        <?php endforeach ?>
        <?php foreach ($purposes as $purpose): ?>
        <div class="col-md-6"><label class="form-label" for="captcha-policy-<?= esc($purpose, 'attr') ?>"><?= esc(lang('Captcha.Purposes.' . str_replace('.', '_', $purpose))) ?></label><select class="form-select" id="captcha-policy-<?= esc($purpose, 'attr') ?>" name="policies[<?= esc($purpose, 'attr') ?>]">
            <?php foreach (['off', 'guests', 'all'] as $policy): ?><option value="<?= $policy ?>" <?= ($oldPolicies[$purpose] ?? $options['policies'][$purpose]) === $policy ? 'selected' : '' ?>><?= esc(lang('Captcha.Settings.' . $policy)) ?></option><?php endforeach ?>
        </select></div>
        <?php endforeach ?>
    </div>
    <button class="btn btn-primary mt-3" type="submit"><?= esc(lang('MCMS.common.save')) ?></button>
</form>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title"><?= esc(lang('Captcha.Settings.statistics')) ?></h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th><?= esc(lang('Captcha.Settings.form')) ?></th><?php foreach (['issued','passed','failed','expired','limited'] as $outcome): ?><th><?= esc(lang('Captcha.Settings.' . $outcome)) ?></th><?php endforeach ?></tr></thead><tbody>
<?php $totals = []; foreach ($statistics as $metric) { $totals[$metric['purpose']][$metric['outcome']] = (int) $metric['total']; } ?>
<?php foreach (array_unique([...$purposes, ...array_keys($totals)]) as $purpose): ?><tr><td class="text-break"><?= esc(in_array($purpose, $purposes, true) ? lang('Captcha.Purposes.' . str_replace('.', '_', $purpose)) : $purpose) ?></td><?php foreach (['issued','passed','failed','expired','limited'] as $outcome): ?><td><?= (int) ($totals[$purpose][$outcome] ?? 0) ?></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
