<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <h5>Benvenuto, <?= esc(session()->get('nome')) ?></h5>
        <p class="text-muted mb-2">
            Ruolo: <span class="badge text-bg-primary"><?= esc(session()->get('ruolo_nome') ?? 'Nessun ruolo assegnato') ?></span>
        </p>
        <p class="text-muted mb-0">Permessi attivi:</p>
        <div>
            <?php foreach (session()->get('permessi') ?? [] as $permesso): ?>
                <span class="badge text-bg-light border me-1 mb-1"><?= esc($permesso) ?></span>
            <?php endforeach; ?>
            <?php if (empty(session()->get('permessi'))): ?>
                <span class="text-muted">Nessuno</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
