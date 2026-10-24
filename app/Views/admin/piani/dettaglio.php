<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?php
$permessiUtente = session()->get('permessi') ?? [];
$puoEseguire    = in_array('esecuzioni.create', $permessiUtente, true);
$chiuso         = $piano['stato'] === 'chiuso';

$badgeCaso = [
    'da_iniziare' => ['secondary', 'Da iniziare'],
    'in_corso'    => ['primary', 'In corso'],
    'con_bug'     => ['danger', 'Con bug'],
    'completato'  => ['success', 'Completato'],
];
$data = static fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';
?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= site_url('admin/piani') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Piani</a>
    <a href="<?= site_url('admin/piani/report/' . $piano['id']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary ms-1"><i class="bi bi-printer"></i> Report</a>
    <span class="ms-2"><?= view('admin/piani/_badge_stato', ['stato' => $piano['stato']], ['debug' => false]) ?></span>
    <span class="text-muted ms-2">
        <?= esc($piano['prodotto_nome'] . ' ' . $piano['versione_nome']) ?> ·
        Tester: <?= esc($piano['assegnatario_nome'] ?? 'non assegnato') ?> ·
        <?= $data($piano['data_inizio']) ?> → <?= $data($piano['data_fine_prevista']) ?>
    </span>
</div>

<?php if ($metriche['completo'] && !$chiuso): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check-circle"></i> Tutti i passaggi sono chiusi: il piano puo essere chiuso.</span>
        <?php if ($puoEseguire): ?>
            <button type="button" class="btn btn-success btn-sm" id="btnChiudiPiano">Chiudi il piano</button>
        <?php endif; ?>
    </div>
<?php elseif ($metriche['a_rischio'] && !$chiuso): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
        <?php if ($metriche['proiezione_fine'] === null || $metriche['proiezione_fine'] <= $piano['data_fine_prevista']): ?>
            La fine prevista (<?= $data($piano['data_fine_prevista']) ?>) e gia passata.
        <?php else: ?>
            Al ritmo attuale il piano finirebbe il <strong><?= $data($metriche['proiezione_fine']) ?></strong>,
            dopo la fine prevista (<?= $data($piano['data_fine_prevista']) ?>).
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title">Avanzamento</h6>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Reale: <strong><?= $metriche['avanzamento_reale'] ?>%</strong> (<?= (int) $metriche['chiusi'] ?>/<?= (int) $metriche['totale'] ?> passaggi chiusi)</span>
                    <span>Atteso oggi: <strong><?= $metriche['avanzamento_atteso'] ?>%</strong></span>
                </div>
                <div class="progress position-relative mt-1" style="height: 22px;">
                    <div class="progress-bar <?= $metriche['completo'] ? 'bg-success' : ($metriche['in_ritardo'] ? 'bg-warning' : 'bg-primary') ?>"
                         style="width: <?= (float) $metriche['avanzamento_reale'] ?>%"><?= $metriche['avanzamento_reale'] ?>%</div>
                    <?php if (!$chiuso): ?>
                        <div class="position-absolute top-0 bottom-0 border-start border-2 border-dark"
                             style="left: <?= (float) $metriche['avanzamento_atteso'] ?>%" title="Avanzamento atteso"></div>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <?php if ($chiuso): ?>
                        <span class="badge text-bg-success">Chiuso il <?= $data($piano['data_fine_effettiva']) ?></span>
                    <?php elseif ($metriche['in_ritardo']): ?>
                        <span class="badge text-bg-danger">In ritardo di <?= $metriche['ritardo_giorni'] ?> giorni</span>
                    <?php else: ?>
                        <span class="badge text-bg-success">Nei tempi</span>
                    <?php endif; ?>
                    <span class="text-muted small ms-1">giorno <?= (int) $metriche['giorni_trascorsi'] ?> di <?= (int) $metriche['giorni_totali'] ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center py-2">
                    <div class="fs-4 fw-bold"><?= (int) $metriche['fermi'] ?></div>
                    <div class="small text-muted">Fermi per bug o blocchi</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center py-2">
                    <div class="fs-4 fw-bold"><?= (int) $metriche['da_riverificare'] ?></div>
                    <div class="small text-muted">Da riverificare</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center py-2">
                    <div class="fs-4 fw-bold"><?= (int) $metriche['da_eseguire'] ?></div>
                    <div class="small text-muted">Da eseguire</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center py-2">
                    <div class="fs-4 fw-bold"><?= $data($metriche['proiezione_fine']) ?></div>
                    <div class="small text-muted">Fine proiettata</div>
                </div></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between">
                    <span>Tempo stimato (extra riverifiche <?= (int) $piano['quota_riverifica'] ?>% incluso)</span>
                    <strong><?= (int) $metriche['stima_minuti'] ?> min</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Tempo effettivo registrato</span>
                    <strong>
                        <?= $metriche['minuti_effettivi'] === null ? '—' : (int) $metriche['minuti_effettivi'] . ' min' ?>
                        <?php if ($metriche['scostamento_pct'] !== null): ?>
                            <span class="badge text-bg-<?= $metriche['scostamento_pct'] > 0 ? 'warning' : 'success' ?>">
                                <?= ($metriche['scostamento_pct'] > 0 ? '+' : '') . $metriche['scostamento_pct'] ?>%
                            </span>
                        <?php endif; ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Casi del piano</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Caso</th><th>Suite</th><th>Stato</th><th>Passaggi chiusi</th>
                        <th class="text-end">Stima</th><th class="text-end">Effettivo</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($casi as $caso): ?>
                        <?php
                        $chiusiCaso = count(array_filter($caso['passaggi'], static fn ($p) => $p['stato'] === 'chiuso'));
                        [$colore, $etichetta] = $badgeCaso[$caso['stato']];
                        ?>
                        <tr>
                            <td><a href="<?= site_url('admin/esecuzioni/caso/' . $caso['id']) ?>"><?= esc($caso['titolo']) ?></a></td>
                            <td><?= esc($caso['suite_nome']) ?></td>
                            <td><span class="badge text-bg-<?= $colore ?>"><?= $etichetta ?></span></td>
                            <td><?= $chiusiCaso ?>/<?= count($caso['passaggi']) ?></td>
                            <td class="text-end"><?= (int) $caso['stima_minuti'] ?> min</td>
                            <td class="text-end"><?= $caso['minuti_effettivi'] === null ? '—' : (int) $caso['minuti_effettivi'] . ' min' ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/esecuzioni/caso/' . $caso['id']) ?>">
                                    <?= $puoEseguire && !$chiuso ? 'Esegui' : 'Apri' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($casi === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">Il piano non contiene casi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('btnChiudiPiano')?.addEventListener('click', function () {
    if (!confirm('Confermi la chiusura del piano?')) {
        return;
    }
    AdminCRUD.post(<?= json_encode(site_url('admin/piani/chiudi/' . $piano['id'])) ?>, new FormData())
        .then(({ json }) => json.success ? location.reload() : AdminCRUD.showAlert('danger', json.message));
});
</script>
<?= $this->endSection() ?>
