<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Dashboard
<?php if (in_array('piani.view', session()->get('permessi') ?? [], true)): ?>
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Piani di test aperti</h5>
        <?php if ($bugAperti !== null): ?>
            <a href="<?= site_url('admin/bug') ?>" class="badge text-bg-<?= $bugAperti > 0 ? 'danger' : 'success' ?> text-decoration-none">
                <?= (int) $bugAperti ?> bug da correggere
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Piano</th><th>Prodotto</th><th>Fine prevista</th><th style="min-width: 160px;">Avanzamento</th><th>Proiezione</th></tr></thead>
                <tbody>
                    <?php foreach ($piani as $piano): ?>
                        <tr>
                            <td><a href="<?= site_url('admin/piani/dettaglio/' . $piano['id']) ?>"><?= esc($piano['nome']) ?></a></td>
                            <td><?= esc($piano['prodotto_nome'] . ' ' . $piano['versione_nome']) ?></td>
                            <td><?= esc(date('d/m/Y', strtotime($piano['data_fine_prevista']))) ?></td>
                            <td><?= view('admin/piani/_barra', ['metriche' => $piano['metriche']], ['debug' => false]) ?></td>
                            <td>
                                <?= $piano['metriche']['proiezione_fine'] ? esc(date('d/m/Y', strtotime($piano['metriche']['proiezione_fine']))) : '—' ?>
                                <?php if ($piano['metriche']['a_rischio']): ?><span class="badge text-bg-danger">a rischio</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($piani === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Nessun piano di test aperto.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?= $this->endSection() ?>

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
