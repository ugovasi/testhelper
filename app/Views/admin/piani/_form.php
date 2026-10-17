<?php
$isEdit = $record !== null;
$casiPerSuite = [];
foreach ($casi as $caso) {
    $casiPerSuite[$caso['prodotto_nome'] . ' / ' . $caso['suite_nome']][] = $caso;
}
?>
<form id="formPiano" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $record['id'] ?>">
    <?php endif; ?>
    <?php if ($bloccato): ?>
        <div class="alert alert-warning py-2">Il piano e chiuso: non si puo modificare.</div>
    <?php endif; ?>

    <div class="mb-3">
        <label for="nome" class="form-label">Nome piano</label>
        <input type="text" name="nome" id="nome" class="form-control" placeholder="es. Regressione 2.4" value="<?= esc($record['nome'] ?? '', 'attr') ?>" required>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="versione_id" class="form-label">Versione</label>
            <select name="versione_id" id="versione_id" class="form-select" required>
                <option value="">Seleziona...</option>
                <?php foreach ($versioni as $v): ?>
                    <option value="<?= (int) $v['id'] ?>" <?= (int) ($record['versione_id'] ?? 0) === (int) $v['id'] ? 'selected' : '' ?>>
                        <?= esc($v['prodotto_nome'] . ' ' . $v['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label for="assegnatario_id" class="form-label">Tester assegnato</label>
            <select name="assegnatario_id" id="assegnatario_id" class="form-select">
                <option value="">Nessuno</option>
                <?php foreach ($tester as $utente): ?>
                    <option value="<?= (int) $utente['id'] ?>" <?= (int) ($record['assegnatario_id'] ?? 0) === (int) $utente['id'] ? 'selected' : '' ?>>
                        <?= esc($utente['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="data_inizio" class="form-label">Inizio</label>
            <input type="date" name="data_inizio" id="data_inizio" class="form-control" value="<?= esc($record['data_inizio'] ?? '', 'attr') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label for="data_fine_prevista" class="form-label">Fine prevista</label>
            <input type="date" name="data_fine_prevista" id="data_fine_prevista" class="form-control" value="<?= esc($record['data_fine_prevista'] ?? '', 'attr') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
            <label for="quota_riverifica" class="form-label">Extra riverifiche (%)</label>
            <input type="number" min="0" max="100" name="quota_riverifica" id="quota_riverifica" class="form-control" value="<?= (int) ($record['quota_riverifica'] ?? 15) ?>" required>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Casi di test</label>
        <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
            <?php foreach ($casiPerSuite as $gruppo => $elenco): ?>
                <div class="fw-bold small text-muted mt-1"><?= esc($gruppo) ?></div>
                <?php foreach ($elenco as $caso): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="casi[]" id="caso_<?= (int) $caso['id'] ?>"
                               value="<?= (int) $caso['id'] ?>" <?= in_array((int) $caso['id'], $casiPiano, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="caso_<?= (int) $caso['id'] ?>">
                            <?= esc($caso['titolo']) ?> <span class="text-muted">(<?= (int) $caso['stima_minuti'] ?> min)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (empty($casi)): ?>
                <p class="text-muted mb-0">Nessun caso di test in libreria. Creane qualcuno dal modulo Casi di test.</p>
            <?php endif; ?>
        </div>
        <div name="casi"></div>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary" <?= $bloccato ? 'disabled' : '' ?>>Salva</button>
    </div>
</form>
