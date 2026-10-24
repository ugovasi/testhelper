<?php
$badge  = ['alta' => 'danger', 'media' => 'warning', 'bassa' => 'secondary'][$caso['priorita']] ?? 'secondary';
$totale = array_sum(array_map(static fn ($p) => (int) $p['stima_minuti'], $passaggi));
$data   = static fn (?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
?>
<div class="row g-2 mb-3">
    <div class="col-md-8">
        <h5 class="mb-1"><?= esc($caso['titolo']) ?></h5>
        <div class="text-muted"><?= esc($caso['prodotto_nome']) ?> / <?= esc($caso['suite_nome']) ?></div>
    </div>
    <div class="col-md-4 text-md-end">
        <span class="badge text-bg-<?= $badge ?>">Priorità <?= esc($caso['priorita']) ?></span>
    </div>
</div>

<dl class="row mb-3">
    <dt class="col-sm-4">Stima totale</dt>
    <dd class="col-sm-8"><strong><?= $totale ?> min</strong> (somma dei passaggi)</dd>

    <dt class="col-sm-4">Passaggi</dt>
    <dd class="col-sm-8"><?= count($passaggi) ?></dd>

    <dt class="col-sm-4">Usato in</dt>
    <dd class="col-sm-8"><?= $piani === 0 ? 'nessun piano' : ($piani === 1 ? '1 piano di test' : $piani . ' piani di test') ?></dd>

    <dt class="col-sm-4">Precondizioni</dt>
    <dd class="col-sm-8"><?= $caso['precondizioni'] ? nl2br(esc($caso['precondizioni'])) : '<span class="text-muted">nessuna</span>' ?></dd>

    <dt class="col-sm-4">Creato</dt>
    <dd class="col-sm-8"><?= $data($caso['created_at']) ?> da <?= esc($caso['creato_da_nome'] ?? 'Sistema') ?></dd>

    <dt class="col-sm-4">Ultima modifica</dt>
    <dd class="col-sm-8"><?= $data($caso['updated_at']) ?> da <?= esc($caso['modificato_da_nome'] ?? '—') ?></dd>
</dl>

<h6>Passaggi</h6>
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
            <?php foreach ($passaggi as $p): ?>
                <tr>
                    <td><?= (int) $p['ordine'] ?></td>
                    <td><?= nl2br(esc($p['azione'])) ?></td>
                    <td><?= $p['risultato_atteso'] ? nl2br(esc($p['risultato_atteso'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end text-nowrap"><?= (int) $p['stima_minuti'] ?> min</td>
                </tr>
            <?php endforeach; ?>
            <?php if ($passaggi === []): ?>
                <tr><td colspan="4" class="text-center text-muted">Nessun passaggio.</td></tr>
            <?php endif; ?>
        </tbody>
        <?php if ($passaggi !== []): ?>
            <tfoot>
                <tr><th colspan="3" class="text-end">Totale</th><th class="text-end text-nowrap"><?= $totale ?> min</th></tr>
            </tfoot>
        <?php endif; ?>
    </table>
</div>

<div class="modal-footer px-0 pb-0 mt-3">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
</div>
