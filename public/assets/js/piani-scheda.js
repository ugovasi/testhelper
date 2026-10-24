/**
 * Pulsante "Dati del piano" della tabella dei piani: carica la scheda di sola lettura
 * nella stessa modale usata per la form e la mostra. Gli handler sono delegati a document
 * perche' i pulsanti vengono creati a ogni redraw della DataTable.
 */
(function () {
    document.addEventListener('click', (evento) => {
        const pulsante = evento.target.closest?.('[data-piano-scheda]');
        if (!pulsante) {
            return;
        }

        const modaleEl = document.getElementById('modalPiani');
        const modale = bootstrap.Modal.getOrCreateInstance(modaleEl);
        const corpo = modaleEl.querySelector('.modal-body');

        modaleEl.querySelector('.modal-title').textContent = 'Piano di test';
        corpo.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
        modale.show();

        fetch(pulsante.dataset.url, { headers: AdminCRUD.csrfHeaders() })
            .then((res) => res.json())
            .then((json) => {
                if (!json.success) {
                    modale.hide();
                    AdminCRUD.showAlert('danger', json.message || 'Errore nel caricamento del piano.');
                    return;
                }
                corpo.innerHTML = json.html;
            })
            .catch(() => {
                modale.hide();
                AdminCRUD.showAlert('danger', 'Errore di comunicazione con il server.');
            });
    });
})();
