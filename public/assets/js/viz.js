/**
 * Interazione dei grafici della dashboard: tooltip al passaggio del mouse o al focus da
 * tastiera sugli elementi con data-tip, e passaggio grafico <-> tabella. I testi dei
 * tooltip si inseriscono sempre con textContent (mai innerHTML).
 */
(function () {
    const tooltip = document.createElement('div');
    tooltip.className = 'viz-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    function riempi(elemento) {
        let righe;
        try {
            righe = JSON.parse(elemento.dataset.tip);
        } catch (e) {
            return false;
        }

        tooltip.replaceChildren();

        const titolo = document.createElement('div');
        titolo.className = 'viz-tooltip-title';
        titolo.textContent = elemento.dataset.tipTitle || '';
        tooltip.appendChild(titolo);

        righe.forEach(([valore, etichetta]) => {
            const riga = document.createElement('div');
            riga.className = 'viz-tooltip-row';
            const v = document.createElement('strong');
            v.textContent = valore;
            const l = document.createElement('span');
            l.textContent = etichetta;
            riga.append(v, l);
            tooltip.appendChild(riga);
        });

        return true;
    }

    function posiziona(x, y) {
        tooltip.hidden = false;
        const margine = 12;
        const { width, height } = tooltip.getBoundingClientRect();
        const sinistra = Math.min(x + margine, window.innerWidth - width - 8);
        const alto = y - height - margine < 8 ? y + margine : y - height - margine;
        tooltip.style.left = Math.max(8, sinistra) + 'px';
        tooltip.style.top = alto + 'px';
    }

    function nascondi() {
        tooltip.hidden = true;
    }

    document.addEventListener('pointermove', (evento) => {
        const bersaglio = evento.target.closest?.('[data-tip]');
        if (bersaglio && riempi(bersaglio)) {
            posiziona(evento.clientX, evento.clientY);
        } else {
            nascondi();
        }
    });

    document.addEventListener('focusin', (evento) => {
        const bersaglio = evento.target.closest?.('[data-tip]');
        if (bersaglio && riempi(bersaglio)) {
            const rect = bersaglio.getBoundingClientRect();
            posiziona(rect.left + rect.width / 2, rect.top);
        }
    });
    document.addEventListener('focusout', nascondi);
    document.addEventListener('scroll', nascondi, true);

    document.addEventListener('click', (evento) => {
        const pulsante = evento.target.closest?.('[data-viz-toggle]');
        if (!pulsante) {
            return;
        }

        const figura = pulsante.closest('[data-viz]');
        const inTabella = pulsante.getAttribute('aria-pressed') !== 'true';

        figura.querySelector('[data-viz-chart]').hidden = inTabella;
        figura.querySelector('[data-viz-table]').hidden = !inTabella;
        pulsante.setAttribute('aria-pressed', String(inTabella));
        pulsante.textContent = inTabella ? 'Grafico' : 'Tabella';
        nascondi();
    });
})();
