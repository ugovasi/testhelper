<?php $isEdit = $utente !== null; ?>
<form id="formUtente" novalidate>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_id" value="<?= (int) $utente['id'] ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" name="nome" id="nome" class="form-control" value="<?= esc($utente['nome'] ?? '', 'attr') ?>" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" value="<?= esc($utente['email'] ?? '', 'attr') ?>" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">
            Password <?= $isEdit ? '<span class="text-muted">(lascia vuoto per non modificarla)</span>' : '' ?>
        </label>
        <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
    </div>

    <div class="mb-3">
        <label for="ruolo_id" class="form-label">Ruolo</label>
        <select name="ruolo_id" id="ruolo_id" class="form-select">
            <option value="">Nessun ruolo</option>
            <?php foreach ($ruoli as $ruolo): ?>
                <option value="<?= (int) $ruolo['id'] ?>" <?= (($utente['ruolo_id'] ?? null) == $ruolo['id']) ? 'selected' : '' ?>>
                    <?= esc($ruolo['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="attivo" id="attivo" class="form-check-input" value="1"
               <?= (!$isEdit || $utente['attivo']) ? 'checked' : '' ?>>
        <label for="attivo" class="form-check-label">Attivo</label>
    </div>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
    </div>
</form>
