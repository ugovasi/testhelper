/**
 * Motore generico per i moduli CRUD con form dentro modali Bootstrap.
 * Ogni pagina indice (utenti/ruoli/permessi) chiama AdminCRUD.initModule(config)
 * passando solo le informazioni specifiche del modulo (URL, colonne DataTable).
 *
 * Gestione CSRF: il token (modalita' 'session' lato server) viene letto la prima
 * volta dal meta tag della pagina, poi tenuto aggiornato leggendo l'header di
 * risposta X-CSRF-TOKEN che App\Filters\CsrfTokenFilter allega a ogni risposta.
 * Cosi' piu' submit consecutivi nella stessa pagina non incontrano mai un token
 * scaduto (vedi CSRF regenerate = true in Config\Security).
 */
const AdminCRUD = (function () {
    let currentToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /**
     * Con security.regenerate il token cambia a ogni richiesta POST, comprese quelle
     * AJAX delle DataTable: il campo nascosto dei form "classici" (es. il logout nella
     * navbar) renderizzato al caricamento della pagina sarebbe scaduto. Prima di ogni
     * invio lo riallineiamo al token piu' recente.
     */
    document.addEventListener('submit', (event) => {
        const nome = document.querySelector('meta[name="csrf-name"]')?.content;
        const campo = nome && event.target.querySelector?.(`input[type="hidden"][name="${nome}"]`);
        if (campo && currentToken) {
            campo.value = currentToken;
        }
    }, true);

    function csrfHeaders(extra) {
        return Object.assign(
            { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': currentToken },
            extra || {}
        );
    }

    function trackTokenFrom(response) {
        const fresh = response.headers.get('X-CSRF-TOKEN');
        if (fresh) {
            currentToken = fresh;
        }
        return response;
    }

    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        if (!container) {
            return;
        }
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.prepend(div);
        setTimeout(() => div.remove(), 5000);
    }

    function escapeHtml(valore) {
        const mappa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

        return String(valore ?? '').replace(/[&<>"']/g, (carattere) => mappa[carattere]);
    }

    /**
     * HTML del pulsante-icona che sostituisce la colonna "Creato il" nelle
     * DataTable dei moduli admin: al click/focus apre un popover Bootstrap con
     * creato il/da e modificato il/da, letti dai campi che il model espone
     * tramite App\Models\BaseModel::conCreatoreModificatore().
     */
    function infoPopoverHtml(riga) {
        const contenuto = [
            ['Creato il', riga.created_at || '—'],
            ['Creato da', riga.creato_da_nome || 'Sistema'],
            ['Modificato il', riga.updated_at || '—'],
            ['Modificato da', riga.modificato_da_nome || '—'],
        ]
            .map(([etichetta, valore]) => `<strong>${escapeHtml(etichetta)}:</strong> ${escapeHtml(valore)}`)
            .join('<br>');

        return `<button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="popover" data-bs-html="true"
            data-bs-trigger="focus" data-bs-placement="left" data-bs-content="${contenuto}"
            aria-label="Informazioni su creazione e modifica">
            <i class="bi bi-info-circle"></i>
        </button>`;
    }

    /**
     * Bootstrap non inizializza da solo i popover su elementi aggiunti dopo il
     * caricamento della pagina: va richiamata a ogni redraw della DataTable.
     */
    function initPopovers(container) {
        container.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => {
            bootstrap.Popover.getOrCreateInstance(el, { html: true, trigger: 'focus', sanitize: true });
        });
    }

    function clearFormErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback[data-crud-error]').forEach((el) => el.remove());
    }

    function showFormErrors(form, errors) {
        clearFormErrors(form);
        Object.keys(errors || {}).forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (!input) {
                return;
            }
            input.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.dataset.crudError = '1';
            feedback.textContent = errors[field];
            (input.closest('.mb-3, .form-check, .col') || input.parentElement).appendChild(feedback);
        });
    }

    function initModule(config) {
        const modalEl = document.getElementById(config.modalId);
        const modal = new bootstrap.Modal(modalEl);
        const modalBody = modalEl.querySelector('.modal-body');
        const modalTitle = modalEl.querySelector('.modal-title');

        const table = $(config.tableSelector).DataTable({
            processing: true,
            serverSide: true,
            ajax: (data, callback) => {
                fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
                    body: $.param(data),
                })
                    .then(trackTokenFrom)
                    .then((res) => res.json())
                    .then(callback);
            },
            columns: config.columns,
            order: config.order || [[0, 'desc']],
            language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/it-IT.json' },
            drawCallback() {
                initPopovers(this.api().table().container());
            },
        });

        function openForm(url, title) {
            modalTitle.textContent = title;
            modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
            modal.show();

            fetch(url, { headers: csrfHeaders() })
                .then(trackTokenFrom)
                .then((res) => res.json())
                .then((json) => {
                    if (!json.success) {
                        modal.hide();
                        showAlert('danger', json.message || 'Errore nel caricamento del form.');
                        return;
                    }
                    modalBody.innerHTML = json.html;
                })
                .catch(() => {
                    modal.hide();
                    showAlert('danger', 'Errore di comunicazione con il server.');
                });
        }

        document.querySelector(config.createTrigger)?.addEventListener('click', () => {
            openForm(config.formUrlNew, config.createLabel || 'Nuovo');
        });

        $(config.tableSelector).on('click', '[data-crud-action="edit"]', function () {
            const id = this.dataset.id;
            openForm(config.formUrlEdit(id), config.editLabel || 'Modifica');
        });

        $(config.tableSelector).on('click', '[data-crud-action="delete"]', function () {
            const id = this.dataset.id;
            const label = this.dataset.label || '';
            if (!confirm(`Confermi l'eliminazione di "${label}"?`)) {
                return;
            }
            fetch(config.deleteUrl(id), { method: 'DELETE', headers: csrfHeaders() })
                .then(trackTokenFrom)
                .then((res) => res.json())
                .then((json) => {
                    showAlert(json.success ? 'success' : 'danger', json.message);
                    if (json.success) {
                        table.ajax.reload(null, false);
                    }
                });
        });

        modalBody.addEventListener('submit', (event) => {
            const form = event.target.closest('form');
            if (!form) {
                return;
            }
            event.preventDefault();

            const submitBtn = form.querySelector('[type="submit"]');
            submitBtn?.setAttribute('disabled', 'disabled');

            const id = form.querySelector('[name="_id"]')?.value;
            const url = id ? config.updateUrl(id) : config.storeUrl;
            const formData = new FormData(form);

            fetch(url, { method: 'POST', headers: csrfHeaders(), body: formData })
                .then(trackTokenFrom)
                .then((res) => res.json().then((json) => ({ status: res.status, json })))
                .then(({ status, json }) => {
                    if (status === 422) {
                        showFormErrors(form, json.errors);
                        return;
                    }
                    if (!json.success) {
                        showAlert('danger', json.message || 'Operazione non riuscita.');
                        return;
                    }
                    modal.hide();
                    showAlert('success', json.message);
                    table.ajax.reload(null, false);
                })
                .catch(() => showAlert('danger', 'Errore di comunicazione con il server.'))
                .finally(() => submitBtn?.removeAttribute('disabled'));
        });

        return table;
    }

    /**
     * POST generico per le pagine non-CRUD (esecuzione dei test, chiusura piano):
     * allega il token CSRF corrente e lo aggiorna dalla risposta, cosi' piu' azioni
     * consecutive nella stessa pagina non incontrano un token scaduto.
     */
    function post(url, body) {
        return fetch(url, { method: 'POST', headers: csrfHeaders(), body })
            .then(trackTokenFrom)
            .then((res) => res.json().then((json) => ({ status: res.status, json })));
    }

    return { initModule, csrfHeaders, showAlert, infoPopoverHtml, post, showFormErrors, clearFormErrors };
})();
