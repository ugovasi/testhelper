<?php
$badgePriorita = ['alta' => 'danger', 'media' => 'warning', 'bassa' => 'secondary'];
$data     = static fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$dataOra  = static fn (?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
$nPassaggi = array_sum(array_map(static fn ($c) => count($c['passaggi']), $casi));
?>
<div class="row g-2 mb-3">
    <div class="col-md-8">
        <h5 class="mb-1"><?= esc($piano['nome']) ?></h5>
        <div class="text-muted"><?= esc($piano['prodotto_nome'] . ' ' . $piano['versione_nome']) ?></div>
    </div>
    <div class="col-md-4 text-md-end">
        <?= view('admin/piani/_badge_stato', ['stato' => $piano['stato']], ['debug' => false]) ?>
    </div>
</div>

<dl class="row mb-3">
    <dt class="col-sm-4">Tester</dt>
    <dd class="col-sm-8"><?= esc($piano['assegnatario_nome'] ?? 'non assegnato') ?></dd>

    <dt class="col-sm-4">Periodo</dt>
    <dd class="col-sm-8">
        <?= $data($piano['data_inizio']) ?> → <?= $data($piano['data_fine_prevista']) ?> (previsto)
        <?php if ($piano['data_fine_effettiva']): ?> · chiuso il <?= $data($piano['data_fine_effettiva']) ?><?php endif; ?>
    </dd>

    <dt class="col-sm-4">Casi e passaggi</dt>
    <dd class="col-sm-8"><?= count($casi) ?> casi, <?= $nPassaggi ?> passaggi</dd>

    <dt class="col-sm-4">Stima di tempo</dt>
    <dd class="col-sm-8">
        <strong><?= (int) $metriche['stima_minuti'] ?> min</strong>
        <span class="text-muted">(somma dei casi + <?= (int) $piano['quota_riverifica'] ?>% per le riverifiche)</span>
    </dd>

    <dt class="col-sm-4">Avanzamento</dt>
    <dd class="col-sm-8"><?= (int) $metriche['chiusi'] ?> su <?= (int) $metriche['totale'] ?> passaggi chiusi (<?= $metriche['avanzamento_reale'] ?>%)</dd>

    <dt class="col-sm-4">Creato</dt>
    <dd class="col-sm-8"><?= $dataOra($piano['created_at']) ?> da <?= esc($piano['creato_da_nome'] ?? 'Sistema') ?></dd>

    <dt class="col-sm-4">Ultima modifica</dt>
    <dd class="col-sm-8"><?= $dataOra($piano['updated_at']) ?> da <?= esc($piano['modificato_da_nome'] ?? '—') ?></dd>
</dl>

<h6>Casi e passaggi</h6>
<?php foreach ($casi as $caso): ?>
    <?php
    $sommaPassaggi = array_sum(array_map(static fn ($p) => (int) $p['stima_minuti'], $caso['passaggi']));
    ?>
    <div class="border rounded mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center px-2 py-1 bg-body-tertiary">
            <div>
                <strong><?= esc($caso['titolo']) ?></strong>
                <span class="text-muted small">· <?= esc($caso['suite_nome']) ?></span>
                <span class="badge text-bg-<?= $badgePriorita[$caso['priorita']] ?? 'secondary' ?>"><?= esc($caso['priorita']) ?></span>
            </div>
            <div class="small text-nowrap">Stima nel piano: <strong><?= (int) $caso['stima_minuti'] ?> min</strong></div>
        </div>
        <?php if ($sommaPassaggi !== (int) $caso['stima_minuti']): ?>
            <div class="small text-warning-emphasis px-2 pt-1">
                <i class="bi bi-info-circle"></i> I passaggi in libreria sommano oggi <?= $sommaPassaggi ?> min: il caso è stato modificato dopo l'inserimento nel piano, che conserva la stima originale.
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 2.5rem;">#</th>
                        <th>Azione</th>
                        <th>Risultato atteso</th>
                        <th class="text-end text-nowrap">Tempo stimato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caso['passaggi'] as $p): ?>
                        <tr>
                            <td><?= (int) $p['ordine'] ?></td>
                            <td><?= nl2br(esc($p['azione'])) ?></td>
                            <td><?= $p['risultato_atteso'] ? nl2br(esc($p['risultato_atteso'])) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end text-nowrap"><?= (int) $p['stima_minuti'] ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($caso['passaggi'] === []): ?>
                        <tr><td colspan="4" class="text-center text-muted">Nessun passaggio.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($casi === []): ?>
    <p class="text-muted">Il piano non contiene casi.</p>
<?php endif; ?>

<div class="modal-footer px-0 pb-0 mt-3">
    <a class="btn btn-outline-primary" href="<?= site_url('admin/piani/dettaglio/' . $piano['id']) ?>"><i class="bi bi-bar-chart-line"></i> Cruscotto</a>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
</div>
