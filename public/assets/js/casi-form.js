/**
 * Elenco dinamico dei passaggi nel form modale dei casi di test: aggiunge e rimuove
 * righe e mantiene gli indici passaggi[N][...] consecutivi. Gli handler sono delegati
 * a document perche' il form viene iniettato nella modale dopo il caricamento pagina.
 */
(function () {
    function rinumera(elenco) {
        elenco.querySelectorAll('.passaggio-riga').forEach((riga, indice) => {
            riga.querySelector('.passaggio-numero').textContent = 'Passaggio ' + (indice + 1);
            riga.querySelectorAll('input').forEach((campo) => {
                campo.name = campo.name.replace(/passaggi\[\d+\]/, 'passaggi[' + indice + ']');
            });
        });
    }

    function aggiornaTotale() {
        const totale = document.getElementById('stimaTotale');
        if (!totale) {
            return;
        }
        totale.textContent = Array.from(document.querySelectorAll('[data-stima-passaggio]'))
            .reduce((somma, campo) => somma + (parseInt(campo.value, 10) || 0), 0);
    }

    function nuovaRiga() {
        const riga = document.createElement('div');
        riga.className = 'border rounded p-2 mb-2 passaggio-riga';
        riga.innerHTML = `
            <input type="hidden" name="passaggi[0][id]" value="">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-bold passaggio-numero"></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-passaggio-rimuovi title="Rimuovi passaggio"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="row g-1">
                <div class="col">
                    <input type="text" name="passaggi[0][azione]" class="form-control form-control-sm mb-1" placeholder="Azione da compiere">
                    <input type="text" name="passaggi[0][risultato_atteso]" class="form-control form-control-sm" placeholder="Risultato atteso">
                </div>
                <div class="col-auto" style="width: 6.5rem;">
                    <div class="input-group input-group-sm">
                        <input type="number" min="1" name="passaggi[0][stima_minuti]" class="form-control" placeholder="Tempo" title="Tempo stimato in minuti" data-stima-passaggio>
                        <span class="input-group-text">min</span>
                    </div>
                </div>
            </div>`;

        return riga;
    }

    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-stima-passaggio]')) {
            aggiornaTotale();
        }
    });

    // Il form viene iniettato nella modale dopo il caricamento: ricalcola il totale all'apertura.
    document.addEventListener('shown.bs.modal', aggiornaTotale);

    document.addEventListener('click', (event) => {
        if (event.target.closest('#btnAggiungiPassaggio')) {
            const elenco = document.getElementById('elencoPassaggi');
            elenco.appendChild(nuovaRiga());
            rinumera(elenco);
            return;
        }

        const rimuovi = event.target.closest('[data-passaggio-rimuovi]');
        if (rimuovi) {
            const elenco = document.getElementById('elencoPassaggi');
            rimuovi.closest('.passaggio-riga').remove();
            rinumera(elenco);
            aggiornaTotale();
        }
    });
})();
