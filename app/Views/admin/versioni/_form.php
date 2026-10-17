<?php $isEdit = $record !== null; ?>
<form id="formVersione" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $record['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="prodotto_id" class="form-label">Prodotto</label>
        <select name="prodotto_id" id="prodotto_id" class="form-select" required>
            <option value="">Seleziona...</option>
            <?php foreach ($prodotti as $prodotto): ?>
                <option value="<?= (int) $prodotto['id'] ?>" <?= (int) ($record['prodotto_id'] ?? 0) === (int) $prodotto['id'] ? 'selected' : '' ?>>
                    <?= esc($prodotto['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="nome" class="form-label">Versione</label>
            <input type="text" name="nome" id="nome" class="form-control" placeholder="es. 2.4" value="<?= esc($record['nome'] ?? '', 'attr') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="data_rilascio_prevista" class="form-label">Rilascio previsto</label>
            <input type="date" name="data_rilascio_prevista" id="data_rilascio_prevista" class="form-control" value="<?= esc($record['data_rilascio_prevista'] ?? '', 'attr') ?>">
        </div>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
