<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="page-header mb-3"><h2 class="page-title"><?= esc(lang('Ratings.Admin.title')) ?></h2></div>
<p><a href="<?= site_url('admin/ratings/settings') ?>"><?= esc(lang('Ratings.Admin.settings')) ?></a></p>
<div class="card"><div class="table-responsive">
<table class="table card-table table-vcenter">
<thead><tr><th>ID</th><th><?= esc(lang('Ratings.Admin.source')) ?></th><th><?= esc(lang('Ratings.Admin.votes')) ?></th><th>UID</th><th><?= esc(lang('Ratings.Admin.delete')) ?></th></tr></thead>
<tbody>
<?php if ($votes === []): ?><tr><td colspan="5"><?= esc(lang('Ratings.Admin.empty')) ?></td></tr><?php endif ?>
<?php foreach ($votes as $vote): ?>
<tr>
<td><?= (int) $vote['id'] ?></td>
<td>
<?php if ($targets[$vote['id']] !== null): ?>
<a href="<?= esc(site_url(ltrim($targets[$vote['id']]['url'], '/')), 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc($targets[$vote['id']]['label']) ?></a>
<?php else: ?><?= esc($vote['target_type'] . ':' . $vote['target_id']) ?><?php endif ?>
</td>
<td><?= (int) $vote['stars'] ?> ★</td>
<td><?= $vote['user_id'] === null ? 'guest' : (int) $vote['user_id'] ?></td>
<td><?php if ($canManage): ?><form method="post" action="<?= site_url('admin/ratings/' . $vote['id'] . '/delete') ?>"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-danger"><?= esc(lang('Ratings.Admin.delete')) ?></button></form><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody>
</table></div></div>
<?php if ($lastPage > 1): ?><nav class="d-flex justify-content-between mt-3">
<div><?php if ($page > 1): ?><a href="<?= site_url('admin/ratings') . '?page=' . ($page - 1) ?>"><?= esc(lang('MCMS.common.previousPage')) ?></a><?php endif ?></div>
<span><?= $page ?> / <?= $lastPage ?></span>
<div><?php if ($page < $lastPage): ?><a href="<?= site_url('admin/ratings') . '?page=' . ($page + 1) ?>"><?= esc(lang('MCMS.common.nextPage')) ?></a><?php endif ?></div>
</nav><?php endif ?>
<?= $this->endSection() ?>
