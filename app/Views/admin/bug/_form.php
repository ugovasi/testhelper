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

    <div class="mb-3" data-allegati-sezione data-bug-id="<?= (int) $bug['id'] ?>">
        <h6><i class="bi bi-paperclip"></i> Allegati <span class="text-muted fw-normal">(<?= count($allegati) ?>)</span></h6>
        <?php if ($allegati === []): ?>
            <p class="text-muted small mb-2">Nessun allegato.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach ($allegati as $a): ?>
                <div class="border rounded p-1 small text-center" style="width: 7.5rem;">
                    <a href="<?= site_url('admin/bug/allegato/' . $a['id']) ?>" target="_blank" rel="noopener" class="text-decoration-none d-block" title="<?= esc($a['nome_originale'], 'attr') ?>">
                        <?php if ($a['immagine']): ?>
                            <img src="<?= site_url('admin/bug/allegato/' . $a['id']) ?>" alt="<?= esc($a['nome_originale'], 'attr') ?>" class="img-fluid rounded" style="max-height: 4.5rem;">
                        <?php else: ?>
                            <i class="bi bi-file-earmark fs-1"></i>
                        <?php endif; ?>
                        <div class="text-truncate"><?= esc($a['nome_originale']) ?></div>
                    </a>
                    <div class="text-muted"><?= $a['dimensione'] >= 1048576 ? round($a['dimensione'] / 1048576, 1) . ' MB' : max(1, (int) round($a['dimensione'] / 1024)) . ' KB' ?></div>
                    <?php if ($puoAllegare): ?>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" data-allegato-elimina="<?= (int) $a['id'] ?>"
                                data-nome="<?= esc($a['nome_originale'], 'attr') ?>">Elimina</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($puoAllegare): ?>
            <input type="file" class="form-control form-control-sm" multiple data-allegati-input data-allegato-carica
                   accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.log,.csv,.json,.zip,.mp4,.webm">
            <div class="form-text">
                Scegli i file o incolla uno screenshot con Ctrl+V. Fino a <?= (int) \App\Libraries\AllegatiBug::MAX_FILE ?> file per volta, max <?= (int) round($limiteAllegati / 1048576) ?> MB ciascuno.
            </div>
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
