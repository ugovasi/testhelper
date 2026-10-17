<?php
$isEdit = $record !== null;
// Una riga vuota di partenza per un nuovo caso.
$righe = $passaggi !== [] ? $passaggi : [['id' => '', 'azione' => '', 'risultato_atteso' => '', 'stima_minuti' => '']];
?>
<form id="formCaso" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $record['id'] ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 mb-3">
            <label for="titolo" class="form-label">Titolo</label>
            <input type="text" name="titolo" id="titolo" class="form-control" value="<?= esc($record['titolo'] ?? '', 'attr') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label for="suite_id" class="form-label">Suite</label>
            <select name="suite_id" id="suite_id" class="form-select" required>
                <option value="">Seleziona...</option>
                <?php foreach ($suite as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($record['suite_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= esc($s['prodotto_nome'] . ' / ' . $s['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="priorita" class="form-label">Priorita</label>
            <select name="priorita" id="priorita" class="form-select">
                <?php foreach (['alta', 'media', 'bassa'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($record['priorita'] ?? 'media') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Stima totale del caso</label>
            <div class="form-control-plaintext"><strong id="stimaTotale">0</strong> min <span class="text-muted">(somma dei passaggi)</span></div>
        </div>
    </div>

    <div class="mb-3">
        <label for="precondizioni" class="form-label">Precondizioni</label>
        <textarea name="precondizioni" id="precondizioni" class="form-control" rows="2"><?= esc($record['precondizioni'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Passaggi</label>
        <div id="elencoPassaggi">
            <?php foreach ($righe as $indice => $riga): ?>
                <div class="border rounded p-2 mb-2 passaggio-riga">
                    <input type="hidden" name="passaggi[<?= $indice ?>][id]" value="<?= esc($riga['id'], 'attr') ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold passaggio-numero">Passaggio <?= $indice + 1 ?></span>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-passaggio-rimuovi title="Rimuovi passaggio"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="row g-1">
                        <div class="col">
                            <input type="text" name="passaggi[<?= $indice ?>][azione]" class="form-control form-control-sm mb-1" placeholder="Azione da compiere" value="<?= esc($riga['azione'], 'attr') ?>">
                            <input type="text" name="passaggi[<?= $indice ?>][risultato_atteso]" class="form-control form-control-sm" placeholder="Risultato atteso" value="<?= esc($riga['risultato_atteso'] ?? '', 'attr') ?>">
                        </div>
                        <div class="col-auto" style="width: 6.5rem;">
                            <div class="input-group input-group-sm">
                                <input type="number" min="1" name="passaggi[<?= $indice ?>][stima_minuti]" class="form-control" placeholder="Tempo" title="Tempo stimato in minuti" value="<?= $riga['stima_minuti'] ? (int) $riga['stima_minuti'] : '' ?>" data-stima-passaggio>
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div name="passaggi"></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAggiungiPassaggio"><i class="bi bi-plus-lg"></i> Aggiungi passaggio</button>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
