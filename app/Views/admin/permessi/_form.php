<?php $isEdit = $permesso !== null; ?>
<form id="formPermesso" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $permesso['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="chiave" class="form-label">Chiave</label>
        <input type="text" name="chiave" id="chiave" class="form-control" placeholder="es. utenti.view"
               pattern="[a-z0-9_.]+" value="<?= esc($permesso['chiave'] ?? '', 'attr') ?>" required>
        <div class="form-text">Solo lettere minuscole, numeri, punto e underscore.</div>
    </div>

    <div class="mb-3">
        <label for="descrizione" class="form-label">Descrizione</label>
        <input type="text" name="descrizione" id="descrizione" class="form-control" value="<?= esc($permesso['descrizione'] ?? '', 'attr') ?>">
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
