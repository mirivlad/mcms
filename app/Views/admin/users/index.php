<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-4">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Core</div>
            <h2 class="page-title">Users</h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Groups</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($users as $entry): ?>
                <tr>
                    <td><?= esc((string) $entry->id) ?></td>
                    <td><?= esc($entry->username ?? '—') ?></td>
                    <td><?= esc($entry->email ?? '—') ?></td>
                    <td><?= esc(implode(', ', $entry->getGroups()) ?: '—') ?></td>
                    <td><?= $entry->isActivated() ? 'Active' : 'Inactive' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
