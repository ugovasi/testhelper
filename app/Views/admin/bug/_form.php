<form id="formBug" novalidate>
    <input type="hidden" name="_id" value="<?= (int) $bug['id'] ?>">

    <h6><?= esc($bug['titolo']) ?></h6>
    <p class="text-muted small mb-2">
        <?= esc($bug['prodotto_nome'] . ' ' . $bug['versione_nome']) ?> · Piano: <?= esc($bug['piano_nome']) ?> ·
        Gravita: <strong><?= esc($bug['gravita']) ?></strong> · Stato: <strong><?= esc(str_replace('_', ' ', $bug['stato'])) ?></strong> ·
        Assegnato a: <?= esc($bug['assegnato_nome'] ?? 'nessuno') ?>
    </p>

    <?php if ($bug['descrizione']): ?>
        <p><?= nl2br(esc($bug['descrizione'])) ?></p>
    <?php endif; ?>

    <div class="border rounded p-2 mb-3 bg-body-tertiary small">
        <div><strong>Caso:</strong> <?= esc($bug['caso_titolo']) ?></div>
        <div><strong>Passaggio <?= (int) $bug['passaggio_ordine'] ?>:</strong> <?= esc($bug['passaggio_azione']) ?></div>
        <?php if ($bug['passaggio_atteso']): ?>
            <div><strong>Risultato atteso:</strong> <?= esc($bug['passaggio_atteso']) ?></div>
        <?php endif; ?>
        <?php if ($bug['note_esecuzione']): ?>
            <div><strong>Note del tester:</strong> <?= esc($bug['note_esecuzione']) ?></div>
        <?php endif; ?>
        <?php if ($bug['note_risoluzione']): ?>
            <div><strong>Note di risoluzione:</strong> <?= esc($bug['note_risoluzione']) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($puoAggiornare && $transizioni !== []): ?>
        <div class="mb-3">
            <label for="stato" class="form-label">Nuovo stato</label>
            <select name="stato" id="stato" class="form-select">
                <?php foreach ($transizioni as $stato): ?>
                    <option value="<?= esc($stato, 'attr') ?>"><?= esc(ucfirst(str_replace('_', ' ', $stato))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="note_risoluzione" class="form-label">Note di risoluzione</label>
            <textarea name="note_risoluzione" id="note_risoluzione" class="form-control" rows="2" placeholder="Cosa e stato corretto (visibile al tester per la riverifica)"></textarea>
        </div>
    <?php elseif ($puoAggiornare): ?>
        <p class="text-muted">Nessuna azione disponibile nello stato attuale<?= $bug['stato'] === 'risolto' ? ': il tester deve riverificare il passaggio' : '' ?>.</p>
    <?php endif; ?>

    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <?php if ($puoAggiornare && $transizioni !== []): ?>
            <button type="submit" class="btn btn-primary">Aggiorna</button>
        <?php endif; ?>
    </div>
</form>
