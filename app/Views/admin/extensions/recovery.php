<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<h2><?= esc(lang('Extensions.recovery')) ?></h2>
<div class="card"><div class="card-body">
<p><?= esc(lang('Extensions.recoveryHint')) ?></p>
<pre class="text-wrap"><?= esc(json_encode($flag, JSON_PRETTY_PRINT)) ?></pre>
<form method="post"><?= csrf_field() ?><button class="btn btn-primary"><?= esc(lang('Extensions.retry')) ?></button></form>
</div></div>
<?= $this->endSection() ?>
