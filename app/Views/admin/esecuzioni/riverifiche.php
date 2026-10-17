<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Riverifiche<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Passaggi da riverificare <?= $tutti ? '(tutti i piani aperti)' : '(i tuoi piani)' ?></h5>
        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/esecuzioni/riverifiche' . ($tutti ? '' : '?tutti=1')) ?>">
            <?= $tutti ? 'Solo i miei piani' : 'Mostra tutti i piani' ?>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>Piano</th><th>Caso</th><th>Passaggio</th><th>Bug risolto</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($righe as $riga): ?>
                        <tr>
                            <td><?= esc($riga['piano']['nome']) ?></td>
                            <td><?= esc($riga['caso']['titolo']) ?></td>
                            <td><?= (int) $riga['passaggio']['ordine'] ?>. <?= esc($riga['passaggio']['azione']) ?></td>
                            <td>
                                #<?= (int) $riga['passaggio']['bug']['id'] ?> <?= esc($riga['passaggio']['bug']['titolo']) ?>
                                <?php if ($riga['passaggio']['bug']['note_risoluzione']): ?>
                                    <div class="small text-muted">Fix: <?= esc($riga['passaggio']['bug']['note_risoluzione']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-primary" href="<?= site_url('admin/esecuzioni/caso/' . $riga['caso']['id']) ?>">Riverifica</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($righe === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Nessun passaggio in attesa di riverifica.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
