<div class="card mb-4">
    <div class="card-header"><h3 class="card-title"><?= esc(lang('Seo.Admin.metadata')) ?></h3></div>
    <form method="post" action="<?= site_url('admin/seo/settings') ?>">
        <?= csrf_field() ?>
        <div class="card-body">
            <p class="text-secondary"><?= esc(lang('Seo.Admin.ruleHint')) ?></p>
            <?php foreach ($rules as $type => $rule): ?>
                <fieldset class="border rounded p-3 mb-3">
                    <legend class="float-none w-auto px-2 h4"><?= esc(lang('Seo.Admin.' . $type)) ?></legend>
                    <?php foreach (['title', 'description', 'keywords'] as $field): ?>
                        <div class="mb-3">
                            <label class="form-label" for="seo-<?= esc($type . '-' . $field, 'attr') ?>"><?= esc(lang('Seo.Admin.' . (in_array($field, ['title', 'description'], true) ? $field . 'Rule' : $field))) ?></label>
                            <input class="form-control" id="seo-<?= esc($type . '-' . $field, 'attr') ?>" name="rules[<?= esc($type, 'attr') ?>][<?= esc($field, 'attr') ?>]" value="<?= esc(old('rules.' . $type . '.' . $field, $rule[$field])) ?>" maxlength="<?= $field === 'keywords' ? 1000 : ($field === 'description' ? 320 : 255) ?>" <?= $field === 'title' ? 'required' : '' ?>>
                        </div>
                    <?php endforeach ?>
                    <label class="form-label" for="seo-<?= esc($type, 'attr') ?>-source"><?= esc(lang('Seo.Admin.keywordsSource')) ?></label>
                    <select class="form-select" id="seo-<?= esc($type, 'attr') ?>-source" name="rules[<?= esc($type, 'attr') ?>][keywords_source]">
                        <?php foreach (['manual', 'tags'] as $source): ?><option value="<?= esc($source) ?>" <?= old('rules.' . $type . '.keywords_source', $rule['keywords_source']) === $source ? 'selected' : '' ?>><?= esc(lang('Seo.Admin.source' . ucfirst($source))) ?></option><?php endforeach ?>
                    </select>
                </fieldset>
            <?php endforeach ?>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><?= esc(lang('MCMS.common.save')) ?></button></div>
    </form>
</div>
