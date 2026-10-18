/**
 * Allegati dei bug:
 *  - Ctrl+V di uno screenshot nella finestra aperta lo aggiunge al campo file visibile;
 *  - nel dettaglio di un bug, la scelta di file li carica subito e l'eliminazione chiede
 *    conferma; poi il dettaglio viene ricaricato per mostrare l'elenco aggiornato.
 * Richiede admin-crud.js (AdminCRUD.post / showAlert).
 */
(function () {
    function campoVisibile() {
        return Array.from(document.querySelectorAll('input[data-allegati-input]'))
            .find((campo) => campo.offsetParent !== null);
    }

    // Screenshot incollati dagli appunti (es. Win+Shift+S, poi Ctrl+V).
    document.addEventListener('paste', (evento) => {
        const campo = campoVisibile();
        const incollati = Array.from(evento.clipboardData?.files || []).filter((f) => f.type.startsWith('image/'));
        if (!campo || incollati.length === 0) {
            return;
        }

        const contenitore = new DataTransfer();
        Array.from(campo.files).forEach((f) => contenitore.items.add(f));
        incollati.forEach((f, i) => {
            // Gli screenshot incollati arrivano tutti come "image.png": diamo un nome utile.
            const nome = 'screenshot-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + (i ? '-' + (i + 1) : '') + '.png';
            contenitore.items.add(new File([f], nome, { type: f.type }));
        });
        campo.files = contenitore.files;
        campo.dispatchEvent(new Event('change', { bubbles: true }));
        evento.preventDefault();
    });

    function ricaricaDettaglio(sezione) {
        const bugId = sezione.dataset.bugId;
        const corpo = sezione.closest('.modal-body');
        const url = location.pathname.replace(/\/admin\/bug.*$/, '/admin/bug') + '/form/' + bugId;

        return fetch(url, { headers: AdminCRUD.csrfHeaders() })
            .then((res) => res.json())
            .then((json) => { if (json.success) { corpo.innerHTML = json.html; } });
    }

    document.addEventListener('change', (evento) => {
        const campo = evento.target.closest?.('[data-allegato-carica]');
        if (!campo || campo.files.length === 0) {
            return;
        }

        const sezione = campo.closest('[data-allegati-sezione]');
        const dati = new FormData();
        Array.from(campo.files).forEach((f) => dati.append('allegati[]', f));
        campo.disabled = true;

        AdminCRUD.post(location.pathname.replace(/\/admin\/bug.*$/, '/admin/bug') + '/allega/' + sezione.dataset.bugId, dati)
            .then(({ json }) => {
                AdminCRUD.showAlert(json.success ? 'success' : 'danger', json.message);
                return json.success ? ricaricaDettaglio(sezione) : (campo.disabled = false, campo.value = '');
            });
    });

    document.addEventListener('click', (evento) => {
        const pulsante = evento.target.closest?.('[data-allegato-elimina]');
        if (!pulsante || !confirm('Eliminare l\'allegato "' + pulsante.dataset.nome + '"?')) {
            return;
        }

        const sezione = pulsante.closest('[data-allegati-sezione]');
        AdminCRUD.post(location.pathname.replace(/\/admin\/bug.*$/, '/admin/bug') + '/elimina-allegato/' + pulsante.dataset.allegatoElimina, new FormData())
            .then(({ json }) => {
                AdminCRUD.showAlert(json.success ? 'success' : 'danger', json.message);
                if (json.success) {
                    return ricaricaDettaglio(sezione);
                }
            });
    });
})();
