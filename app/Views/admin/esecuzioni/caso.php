<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?php
$permessiUtente = session()->get('permessi') ?? [];
// Ora del server (non del browser) come valore predefinito: il controllo "non nel futuro"
// si fa sul server, quindi le due ore devono essere confrontabili anche con fusi diversi.
$adesso = date('Y-m-d\TH:i:s');
$puoEseguire    = in_array('esecuzioni.create', $permessiUtente, true) && !$chiuso;
// Gli allegati si scaricano da BugController, che richiede bug.view.
$puoVedereAllegati = in_array('bug.view', $permessiUtente, true);

$badge = [
    'da_eseguire'     => ['secondary', 'Da eseguire'],
    'chiuso'          => ['success', 'Chiuso'],
    'fallito'         => ['danger', 'Fallito'],
    'da_riverificare' => ['warning', 'Da riverificare'],
    'bloccato'        => ['dark', 'Bloccato'],
    'saltato'         => ['info', 'Saltato'],
];

// Il primo passaggio ancora eseguibile e' quello "corrente", evidenziato.
$corrente = null;
foreach ($caso['passaggi'] as $p) {
    if ($p['eseguibile']) {
        $corrente = $p['passaggio_id'];
        break;
    }
}
?>

<?= $this->section('content') ?>
<div class="mb-3">
    <a href="<?= site_url('admin/piani/dettaglio/' . $piano['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc($piano['nome']) ?></a>
    <span class="text-muted ms-2"><?= esc($piano['prodotto_nome'] . ' ' . $piano['versione_nome']) ?> · <?= esc($caso['suite_nome']) ?></span>
    <span class="text-muted ms-2">Tempo: <strong><?= $caso['minuti_effettivi'] === null ? '—' : (int) $caso['minuti_effettivi'] ?></strong> / <?= (int) $caso['stima_minuti'] ?> min stimati</span>
    <?php if ($chiuso): ?><span class="badge text-bg-success ms-2">Piano chiuso</span><?php endif; ?>
</div>

<?php if ($precondizioni): ?>
    <div class="alert alert-secondary py-2"><strong>Precondizioni:</strong> <?= nl2br(esc($precondizioni)) ?></div>
<?php endif; ?>

<?php foreach ($caso['passaggi'] as $p): ?>
    <?php [$colore, $etichetta] = $badge[$p['stato']]; ?>
    <div class="card mb-2 <?= $corrente === $p['passaggio_id'] && $puoEseguire ? 'border-primary' : '' ?>">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge text-bg-<?= $colore ?> me-1"><?= $etichetta ?></span>
                    <strong>Passaggio <?= (int) $p['ordine'] ?>:</strong> <?= esc($p['azione']) ?>
                    <span class="text-muted small">(stima <?= (int) $p['stima_minuti'] ?> min)</span>
                    <?php if ($p['risultato_atteso']): ?>
                        <div class="text-muted small">Atteso: <?= esc($p['risultato_atteso']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($p['esecuzioni'] as $e): ?>
                        <div class="small text-muted">
                            Tentativo <?= (int) $e['tentativo'] ?>: <strong><?= esc($e['esito']) ?></strong>
                            · <?= esc(date('d/m/Y H:i', strtotime($e['eseguito_il']))) ?>
                            <?php if ($e['minuti_impiegati'] !== null): ?> · <?= (int) $e['minuti_impiegati'] ?> min<?php endif; ?>
                            <?php if ($e['note']): ?> — <?= esc($e['note']) ?><?php endif; ?>
                            <?php if ($puoEseguire): ?>
                                <a href="#" class="ms-1" data-modifica-esecuzione
                                   data-id="<?= (int) $e['id'] ?>"
                                   data-esito="<?= esc($e['esito'], 'attr') ?>"
                                   data-minuti="<?= esc((string) ($e['minuti_impiegati'] ?? ''), 'attr') ?>"
                                   data-note="<?= esc((string) ($e['note'] ?? ''), 'attr') ?>"
                                   data-quando="<?= esc(date('Y-m-d\TH:i', strtotime($e['eseguito_il'])), 'attr') ?>"
                                   data-titolo="Passaggio <?= (int) $p['ordine'] ?>, tentativo <?= (int) $e['tentativo'] ?>"><i class="bi bi-pencil"></i> modifica</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($p['minuti_effettivi'] !== null && count($p['esecuzioni']) > 1): ?>
                        <div class="small text-muted">Tempo totale sul passaggio: <?= (int) $p['minuti_effettivi'] ?> min</div>
                    <?php endif; ?>
                    <?php if ($p['bug']): ?>
                        <div class="small">
                            <i class="bi bi-bug"></i> Bug #<?= (int) $p['bug']['id'] ?> — <?= esc($p['bug']['titolo']) ?>
                            <span class="badge text-bg-light border"><?= esc(str_replace('_', ' ', $p['bug']['stato'])) ?></span>
                            <?php if ($p['stato'] === 'da_riverificare' && $p['bug']['note_risoluzione']): ?>
                                <span class="text-muted">· Fix: <?= esc($p['bug']['note_risoluzione']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($puoVedereAllegati && ($allegatiPerBug[$p['bug']['id']] ?? []) !== []): ?>
                            <div class="small">
                                <i class="bi bi-paperclip"></i>
                                <?php foreach ($allegatiPerBug[$p['bug']['id']] as $a): ?>
                                    <a href="<?= site_url('admin/bug/allegato/' . $a['id']) ?>" target="_blank" rel="noopener" class="me-2"><?= esc($a['nome_originale']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($puoEseguire && $p['eseguibile']): ?>
                    <div class="btn-group btn-group-sm ms-2 flex-shrink-0">
                        <?php foreach (['passato' => ['success', 'Passato'], 'fallito' => ['danger', 'Fallito'], 'bloccato' => ['dark', 'Bloccato'], 'saltato' => ['info', 'Salta']] as $esito => [$c, $l]): ?>
                            <button type="button" class="btn btn-outline-<?= $c ?>" data-esito="<?= $esito ?>"
                                    data-passaggio="<?= (int) $p['passaggio_id'] ?>" data-ha-bug="<?= $p['bug'] ? 1 : 0 ?>"
                                    data-numero="<?= (int) $p['ordine'] ?>"><?= $l ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($puoEseguire): ?>
    <div class="modal fade" id="modalEsito" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formEsito" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="titoloEsito">Esito</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="piano_caso_id" value="<?= (int) $caso['id'] ?>">
                        <input type="hidden" name="passaggio_id">
                        <input type="hidden" name="esito">

                        <div class="mb-3">
                            <label for="eseguito_il" class="form-label">Data e ora</label>
                            <input type="datetime-local" class="form-control" name="eseguito_il" id="eseguito_il" max="<?= esc(date('Y-m-d\TH:i'), 'attr') ?>">
                        </div>

                        <div class="mb-3" id="bloccoMinuti">
                            <label for="minuti_impiegati" class="form-label">Tempo impiegato (minuti)</label>
                            <input type="number" min="0" class="form-control" name="minuti_impiegati" id="minuti_impiegati" required>
                        </div>

                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea name="note" id="note" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3 d-none" id="bloccoAllegati">
                            <label for="allegati" class="form-label"><i class="bi bi-paperclip"></i> Allegati (screenshot, log, ...)</label>
                            <input type="file" class="form-control" name="allegati[]" id="allegati" multiple data-allegati-input
                                   accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.log,.csv,.json,.zip,.mp4,.webm">
                            <div class="form-text">
                                Fino a <?= (int) \App\Libraries\AllegatiBug::MAX_FILE ?> file, max <?= (int) round($limiteAllegati / 1048576) ?> MB ciascuno.
                                Puoi anche incollare uno screenshot con Ctrl+V.
                            </div>
                        </div>

                        <div id="bloccoBug" class="d-none border-top pt-3">
                            <h6><i class="bi bi-bug"></i> Nuovo bug</h6>
                            <div class="mb-3">
                                <label for="bug_titolo" class="form-label">Titolo</label>
                                <input type="text" class="form-control" name="bug_titolo" id="bug_titolo">
                            </div>
                            <div class="mb-3">
                                <label for="bug_descrizione" class="form-label">Descrizione</label>
                                <textarea class="form-control" name="bug_descrizione" id="bug_descrizione" rows="2"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bug_gravita" class="form-label">Gravita</label>
                                    <select class="form-select" name="bug_gravita" id="bug_gravita">
                                        <?php foreach (['bassa', 'media', 'alta', 'critica'] as $g): ?>
                                            <option value="<?= $g ?>" <?= $g === 'media' ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="bug_assegnato_a" class="form-label">Assegna a</label>
                                    <select class="form-select" name="bug_assegnato_a" id="bug_assegnato_a">
                                        <option value="">Nessuno</option>
                                        <?php foreach ($sviluppatori as $u): ?>
                                            <option value="<?= (int) $u['id'] ?>"><?= esc($u['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <p id="avvisoRiapertura" class="d-none text-danger small mb-0">Il passaggio ha un bug gia risolto: se fallisce di nuovo il bug verra riaperto.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Registra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalModifica" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formModifica" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="titoloModifica">Modifica esecuzione</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">L'esito non si modifica: per cambiarlo registra un nuovo tentativo.</p>
                        <div class="mb-3">
                            <label for="mod_eseguito_il" class="form-label">Data e ora</label>
                            <input type="datetime-local" class="form-control" name="eseguito_il" id="mod_eseguito_il" required>
                        </div>
                        <div class="mb-3" id="modBloccoMinuti">
                            <label for="mod_minuti" class="form-label">Tempo impiegato (minuti)</label>
                            <input type="number" min="0" class="form-control" name="minuti_impiegati" id="mod_minuti">
                        </div>
                        <div class="mb-3">
                            <label for="mod_note" class="form-label">Note</label>
                            <textarea name="note" id="mod_note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary">Salva</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($puoEseguire): ?>
<script src="<?= base_url('assets/js/allegati.js') ?>"></script>
<script>
(function () {
    const modalEl = document.getElementById('modalEsito');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('formEsito');
    const urlRegistra = <?= json_encode(site_url('admin/esecuzioni/registra')) ?>;
    const urlModifica = <?= json_encode(site_url('admin/esecuzioni/modifica')) ?>;

    // Ora del server al caricamento + tempo trascorso da allora, come "ora di parete":
    // niente conversioni di fuso tra browser e server.
    const adesoServer = new Date(<?= json_encode($adesso) ?>);
    const caricataIl = Date.now();
    const pad = (n) => String(n).padStart(2, '0');
    function adessoLocale() {
        const d = new Date(adesoServer.getTime() + (Date.now() - caricataIl));
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    document.querySelectorAll('[data-esito]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const esito = btn.dataset.esito;
            const haBug = btn.dataset.haBug === '1';

            AdminCRUD.clearFormErrors(form);
            form.reset();
            form.elements.passaggio_id.value = btn.dataset.passaggio;
            form.elements.esito.value = esito;
            form.elements.eseguito_il.value = adessoLocale();
            form.elements.eseguito_il.max = adessoLocale();
            document.getElementById('titoloEsito').textContent = 'Passaggio ' + btn.dataset.numero + ': ' + esito;
            document.getElementById('bloccoBug').classList.toggle('d-none', !(esito === 'fallito' && !haBug));
            document.getElementById('bloccoAllegati').classList.toggle('d-none', esito !== 'fallito');
            document.getElementById('avvisoRiapertura').classList.toggle('d-none', !(esito === 'fallito' && haBug));

            // Il tempo impiegato si registra a ogni tentativo; per "salta" non serve.
            document.getElementById('bloccoMinuti').classList.toggle('d-none', esito === 'saltato');
            modal.show();
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        invia();
    });

    const modalModifica = new bootstrap.Modal(document.getElementById('modalModifica'));
    const formModifica = document.getElementById('formModifica');
    let esecuzioneInModifica = null;

    document.querySelectorAll('[data-modifica-esecuzione]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            AdminCRUD.clearFormErrors(formModifica);
            esecuzioneInModifica = link.dataset.id;
            document.getElementById('titoloModifica').textContent = 'Modifica: ' + link.dataset.titolo;
            formModifica.elements.eseguito_il.value = link.dataset.quando;
            formModifica.elements.eseguito_il.max = adessoLocale();
            formModifica.elements.minuti_impiegati.value = link.dataset.minuti;
            formModifica.elements.note.value = link.dataset.note;
            document.getElementById('modBloccoMinuti').classList.toggle('d-none', link.dataset.esito === 'saltato');
            modalModifica.show();
        });
    });

    formModifica.addEventListener('submit', (event) => {
        event.preventDefault();
        AdminCRUD.post(urlModifica + '/' + esecuzioneInModifica, new FormData(formModifica)).then(({ status, json }) => {
            if (status === 422 && json.errors) {
                AdminCRUD.showFormErrors(formModifica, json.errors);
                return;
            }
            if (!json.success) {
                modalModifica.hide();
                AdminCRUD.showAlert('danger', json.message);
                return;
            }
            location.reload();
        });
    });

    function invia() {
        AdminCRUD.post(urlRegistra, new FormData(form)).then(({ status, json }) => {
            if (status === 422 && json.errors) {
                AdminCRUD.showFormErrors(form, json.errors);
                return;
            }
            if (!json.success) {
                modal.hide();
                AdminCRUD.showAlert('danger', json.message);
                return;
            }
            location.reload();
        });
    }

})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
