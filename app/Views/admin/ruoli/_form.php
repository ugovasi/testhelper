<?php
$isEdit = $ruolo !== null;
// Raggruppa i permessi per modulo (prefisso prima del punto nella chiave) per una UI piu' leggibile.
$permessiPerModulo = [];
foreach ($permessi as $permesso) {
    $modulo = strstr($permesso['chiave'], '.', true) ?: $permesso['chiave'];
    $permessiPerModulo[$modulo][] = $permesso;
}
?>
<form id="formRuolo" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $ruolo['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="nome" class="form-label">Nome ruolo</label>
        <input type="text" name="nome" id="nome" class="form-control" value="<?= esc($ruolo['nome'] ?? '', 'attr') ?>" required>
    </div>

    <div class="mb-3">
        <label for="descrizione" class="form-label">Descrizione</label>
        <textarea name="descrizione" id="descrizione" class="form-control" rows="2"><?= esc($ruolo['descrizione'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Permessi</label>
        <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
            <?php foreach ($permessiPerModulo as $modulo => $gruppo): ?>
                <div class="fw-bold text-capitalize small text-muted mt-1"><?= esc($modulo) ?></div>
                <?php foreach ($gruppo as $permesso): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="permessi[]"
                               id="permesso_<?= (int) $permesso['id'] ?>" value="<?= (int) $permesso['id'] ?>"
                               <?= in_array($permesso['chiave'], $permessiAssegnati, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="permesso_<?= (int) $permesso['id'] ?>">
                            <?= esc($permesso['chiave']) ?>
                            <?php if ($permesso['descrizione']): ?>
                                <span class="text-muted">- <?= esc($permesso['descrizione']) ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (empty($permessi)): ?>
                <p class="text-muted mb-0">Nessun permesso disponibile. Creane uno prima dal modulo Permessi.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
