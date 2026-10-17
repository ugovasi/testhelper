<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Guida<?= $this->endSection() ?>

<?php
$nomeRuolo = session()->get('ruolo_nome') ?? 'Nessun ruolo assegnato';
?>

<?= $this->section('styles') ?>
<style>
    .guida-diagramma { overflow-x: auto; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .375rem; padding: .75rem; text-align: center; }
    .guida-diagramma svg { max-width: 100%; height: auto; }
    .guida-sezione { scroll-margin-top: 4rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <p class="mb-1">
            Ruolo: <span class="badge text-bg-primary"><?= esc($nomeRuolo) ?></span>
            · Permessi attivi: <strong><?= count($permessi) ?></strong>
        </p>
        <p class="text-muted mb-2">
            Questa guida mostra solo le aree in cui il tuo ruolo puo fare qualcosa. Dentro ogni area,
            le operazioni senza il segno verde richiedono un permesso che non hai: chiedilo a un amministratore.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($sezioni as $sezione): ?>
                <a class="btn btn-sm btn-outline-secondary" href="#<?= esc($sezione['id'], 'attr') ?>">
                    <i class="<?= esc($sezione['icona'], 'attr') ?>"></i> <?= esc($sezione['titolo']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php foreach ($sezioni as $sezione): ?>
    <div class="card guida-sezione" id="<?= esc($sezione['id'], 'attr') ?>">
        <div class="card-header">
            <h5 class="mb-0"><i class="<?= esc($sezione['icona'], 'attr') ?>"></i> <?= esc($sezione['titolo']) ?></h5>
        </div>
        <div class="card-body">
            <p><?= esc($sezione['intro']) ?></p>

            <?php if ($sezione['operazioni'] !== []): ?>
                <h6>Operazioni</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0">
                        <thead><tr><th style="width: 2rem;"></th><th>Operazione</th><th>Cosa puoi fare</th><th>Permesso</th></tr></thead>
                        <tbody>
                            <?php foreach ($sezione['operazioni'] as $operazione): ?>
                                <tr class="<?= $operazione['disponibile'] ? '' : 'text-muted' ?>">
                                    <td>
                                        <?php if ($operazione['disponibile']): ?>
                                            <i class="bi bi-check-circle-fill text-success" title="Disponibile per il tuo ruolo"></i>
                                        <?php else: ?>
                                            <i class="bi bi-lock" title="Richiede un permesso che non hai"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc($operazione['etichetta']) ?></td>
                                    <td><?= esc($operazione['descrizione']) ?></td>
                                    <td><code><?= esc($operazione['permesso']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($sezione['passi'] !== []): ?>
                <h6>Come si fa</h6>
                <ol>
                    <?php foreach ($sezione['passi'] as $passo): ?>
                        <li><?= esc($passo) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php foreach ($sezione['diagrammi'] as $diagramma): ?>
                <h6 class="mt-3"><?= esc($diagramma['titolo']) ?></h6>
                <div class="guida-diagramma" data-diagramma>
                    <script type="application/json" data-codice><?= json_encode($diagramma['codice'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                    <span class="text-muted small">Caricamento del diagramma...</span>
                </div>
            <?php endforeach; ?>

            <?php if ($sezione['note'] !== []): ?>
                <ul class="text-muted small mt-3 mb-0">
                    <?php foreach ($sezione['note'] as $nota): ?>
                        <li><?= esc($nota) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.2/dist/mermaid.min.js"></script>
<script>
(function () {
    const contenitori = document.querySelectorAll('[data-diagramma]');

    // Se la libreria non e' raggiungibile (rete bloccata) si mostra il testo del diagramma.
    function mostraTesto(contenitore, codice) {
        const pre = document.createElement('pre');
        pre.className = 'text-start small mb-0';
        pre.textContent = codice;
        contenitore.replaceChildren(pre);
    }

    if (typeof mermaid === 'undefined') {
        contenitori.forEach((c) => mostraTesto(c, JSON.parse(c.querySelector('[data-codice]').textContent)));
        return;
    }

    mermaid.initialize({ startOnLoad: false, theme: 'neutral', securityLevel: 'strict', flowchart: { useMaxWidth: true, htmlLabels: true } });

    contenitori.forEach((contenitore, indice) => {
        const codice = JSON.parse(contenitore.querySelector('[data-codice]').textContent);

        mermaid.render('diagramma-' + indice, codice)
            .then(({ svg }) => { contenitore.innerHTML = svg; })
            .catch(() => mostraTesto(contenitore, codice));
    });
})();
</script>
<?= $this->endSection() ?>
