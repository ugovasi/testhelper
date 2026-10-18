<?php
/**
 * Grafico a barre orizzontali (una riga per categoria) con il gemello in tabella.
 *
 * @var string $titolo
 * @var string $sottotitolo
 * @var string $unita              es. "bug"
 * @var list<array{label: string, valore: int, colore?: string}> $righe
 */
$massimo = max(1, ...array_column($righe, 'valore'));
$totale  = array_sum(array_column($righe, 'valore'));
?>
<figure class="viz-figure" data-viz>
    <figcaption class="viz-head">
        <div>
            <strong><?= esc($titolo) ?></strong>
            <div class="viz-sub"><?= esc($sottotitolo) ?></div>
        </div>
        <button type="button" class="btn btn-sm btn-link viz-toggle" data-viz-toggle aria-pressed="false">Tabella</button>
    </figcaption>

    <div data-viz-chart>
        <?php if ($totale === 0): ?>
            <p class="viz-empty">Nessun dato da mostrare.</p>
        <?php else: ?>
            <div class="viz-hbars" role="img" aria-label="<?= esc($titolo, 'attr') ?>">
                <?php foreach ($righe as $riga): ?>
                    <div class="viz-hrow viz-hit" tabindex="0"
                         data-tip-title="<?= esc($riga['label'], 'attr') ?>"
                         data-tip="<?= esc(json_encode([[(string) $riga['valore'], $unita]]), 'attr') ?>">
                        <span class="viz-hlabel"><?= esc($riga['label']) ?></span>
                        <span class="viz-htrack">
                            <?php if ($riga['valore'] > 0): ?>
                                <span class="viz-hbar" style="width: <?= round($riga['valore'] / $massimo * 88, 2) ?>%; background: <?= esc($riga['colore'] ?? 'var(--series-1)', 'attr') ?>;"></span>
                            <?php endif; ?>
                            <span class="viz-hval"><?= (int) $riga['valore'] ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-viz-table hidden>
        <table class="table table-sm mb-0">
            <thead><tr><th><?= esc($titolo) ?></th><th class="text-end"><?= esc(ucfirst($unita)) ?></th></tr></thead>
            <tbody>
                <?php foreach ($righe as $riga): ?>
                    <tr><td><?= esc($riga['label']) ?></td><td class="text-end"><?= (int) $riga['valore'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</figure>
