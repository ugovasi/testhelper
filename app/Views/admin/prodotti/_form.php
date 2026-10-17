<?php $isEdit = $record !== null; ?>
<form id="formProdotto" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $record['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="nome" class="form-label">Nome prodotto</label>
        <input type="text" name="nome" id="nome" class="form-control" value="<?= esc($record['nome'] ?? '', 'attr') ?>" required>
    </div>

    <div class="mb-3">
        <label for="descrizione" class="form-label">Descrizione</label>
        <textarea name="descrizione" id="descrizione" class="form-control" rows="2"><?= esc($record['descrizione'] ?? '') ?></textarea>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" name="attivo" id="attivo" value="1"
               <?= !$isEdit || $record['attivo'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="attivo">Prodotto attivo</label>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
