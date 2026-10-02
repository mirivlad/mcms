<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2 class="page-title mb-3"><?= esc(lang('Seo.Admin.title')) ?></h2>
<p><a href="<?= site_url('sitemap.xml') ?>" target="_blank" rel="noopener"><?= esc(lang('Seo.Admin.sitemap')) ?></a></p>
<p class="text-secondary"><?= esc(lang('Seo.Admin.redirectHint')) ?></p>
<div class="card"><div class="table-responsive"><table class="table card-table table-vcenter">
    <thead><tr><th><?= esc(lang('Seo.Admin.source')) ?></th><th><?= esc(lang('Seo.Admin.target')) ?></th><th></th></tr></thead><tbody>
    <?php if ($redirects === []): ?><tr><td colspan="3"><?= esc(lang('Seo.Admin.empty')) ?></td></tr><?php endif ?>
    <?php foreach ($redirects as $redirect): ?>
        <?php $target = service('mcmsNavigation')->resolve($redirect['provider'], $redirect['resource_id']); ?>
        <tr><td><code><?= esc($redirect['source_path']) ?></code></td><td><?= esc($target['url'] ?? lang('Seo.Admin.unavailable')) ?></td>
            <td><form method="post" action="<?= site_url('admin/seo/delete') ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Seo.Admin.deleteConfirm')), 'attr') ?>);">
                <?= csrf_field() ?><input type="hidden" name="source" value="<?= esc($redirect['source_path'], 'attr') ?>"><button class="btn btn-outline-danger btn-sm"><?= esc(lang('MCMS.common.delete')) ?></button>
            </form></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table></div></div>
<?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between mt-3" aria-label="<?= esc(lang('Seo.Admin.title'), 'attr') ?>">
    <div><?php if ($page > 1): ?><a href="<?= site_url('admin/seo') . '?page=' . ($page - 1) ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div><span><?= (int) $page ?> / <?= (int) $lastPage ?></span><div><?php if ($page < $lastPage): ?><a href="<?= site_url('admin/seo') . '?page=' . ($page + 1) ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav><?php endif ?>
<?= $this->endSection() ?>
