<?php
/**
 * Statistiche della dashboard: tile con i numeri chiave e grafici in HTML/CSS (nessuna
 * libreria). Ogni grafico ha tooltip e un gemello in tabella; i colori sono definiti in
 * public/assets/css/viz.css.
 *
 * @var array<string, mixed>|null $statTest
 * @var array<string, mixed>|null $statBug
 */
$num = static fn ($v) => number_format((float) $v, 0, ',', '.');

$durata = static function (int $minuti) use ($num): string {
    if ($minuti < 60) {
        return $minuti . ' min';
    }

    return $num(intdiv($minuti, 60)) . ' h ' . str_pad((string) ($minuti % 60), 2, '0', STR_PAD_LEFT) . ' min';
};

// L'eroe e' uno solo per pagina: il primo tile che compare.
$haEroe = false;
$tile = static function (string $etichetta, string $valore, string $nota) use (&$haEroe): string {
    $classe = $haEroe ? '' : ' viz-hero';
    $haEroe = true;

    return '<div class="col-6 col-lg-3"><div class="viz-tile"><div class="viz-tile-label">' . esc($etichetta)
        . '</div><div class="viz-tile-value' . $classe . '">' . esc($valore) . '</div><div class="viz-tile-note">' . esc($nota) . '</div></div></div>';
};

$giorniIt = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
?>
<div class="viz-root">

<?php if ($statTest !== null): ?>
    <?php $p = $statTest['passaggi']; $t = $statTest['piani']; ?>
    <h6 class="viz-section">Test</h6>
    <div class="row g-3 mb-3">
        <?php $percentuale = rtrim(rtrim(number_format($p['percentuale'], 1, ',', '.'), '0'), ',') . '%'; // 73,5% / 100% ?>
        <?= $tile('Passaggi chiusi', $percentuale, $num($p['chiusi']) . ' su ' . $num($p['totale']) . ' nei piani aperti') ?>
        <?= $tile('Piani aperti', $num($t['aperti']), $num($t['chiusi']) . ' chiusi in tutto') ?>
        <?= $tile('Esecuzioni registrate', $num($t['esecuzioni']), 'tentativi, riverifiche comprese') ?>
        <?= $tile('Tempo impiegato', $durata($t['minuti']), 'somma di tutti i tentativi') ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <?php
            $segmenti = [
                ['label' => 'Chiusi', 'icona' => 'bi-check-circle-fill', 'colore' => 'var(--st-good)', 'valore' => $p['chiusi']],
                ['label' => 'Da riverificare', 'icona' => 'bi-arrow-repeat', 'colore' => 'var(--st-warning)', 'valore' => $p['da_riverificare']],
                ['label' => 'Fermi (bug o blocchi)', 'icona' => 'bi-exclamation-octagon-fill', 'colore' => 'var(--st-critical)', 'valore' => $p['fermi']],
                ['label' => 'Ancora da fare', 'icona' => 'bi-circle', 'colore' => 'var(--neutral)', 'valore' => $p['da_fare']],
            ];
            ?>
            <figure class="viz-figure" data-viz>
                <figcaption class="viz-head">
                    <div>
                        <strong>Stato dei passaggi</strong>
                        <div class="viz-sub">Nei piani aperti</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link viz-toggle" data-viz-toggle aria-pressed="false">Tabella</button>
                </figcaption>

                <div data-viz-chart>
                    <?php if ($p['totale'] === 0): ?>
                        <p class="viz-empty">Nessun passaggio nei piani aperti.</p>
                    <?php else: ?>
                        <div class="viz-stack" role="img" aria-label="Stato dei passaggi nei piani aperti">
                            <?php foreach ($segmenti as $s): ?>
                                <?php if ($s['valore'] > 0): ?>
                                    <div class="viz-seg viz-hit" tabindex="0" style="flex: <?= (int) $s['valore'] ?> 1 0; background: <?= $s['colore'] ?>;"
                                         data-tip-title="<?= esc($s['label'], 'attr') ?>"
                                         data-tip="<?= esc(json_encode([[(string) $s['valore'], 'passaggi'], [round($s['valore'] / $p['totale'] * 100) . '%', 'del totale']]), 'attr') ?>"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <ul class="viz-legend">
                            <?php foreach ($segmenti as $s): ?>
                                <li>
                                    <span class="viz-swatch" style="background: <?= $s['colore'] ?>;"></span>
                                    <i class="bi <?= $s['icona'] ?>" aria-hidden="true"></i>
                                    <span><?= esc($s['label']) ?></span>
                                    <strong><?= $num($s['valore']) ?></strong>
                                    <span class="viz-muted">(<?= round($s['valore'] / $p['totale'] * 100) ?>%)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div data-viz-table hidden>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Stato</th><th class="text-end">Passaggi</th></tr></thead>
                        <tbody>
                            <?php foreach ($segmenti as $s): ?>
                                <tr><td><?= esc($s['label']) ?></td><td class="text-end"><?= $num($s['valore']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>

        <div class="col-lg-7">
            <?php
            $serie  = $statTest['esecuzioni'];
            $massimoGiorno = max(array_column($serie, 'totale'));
            // Scala a multipli di 4, cosi' anche la tacca centrale e un numero intero.
            $scala  = max(4, (int) (ceil($massimoGiorno / 4) * 4));
            $ultimo = count($serie) - 1;
            $totaleSerie = array_sum(array_column($serie, 'totale'));
            ?>
            <figure class="viz-figure" data-viz>
                <figcaption class="viz-head">
                    <div>
                        <strong>Esecuzioni per giorno</strong>
                        <div class="viz-sub">Ultimi <?= count($serie) ?> giorni, <?= $num($totaleSerie) ?> tentativi</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link viz-toggle" data-viz-toggle aria-pressed="false">Tabella</button>
                </figcaption>

                <div data-viz-chart>
                    <?php if ($totaleSerie === 0): ?>
                        <p class="viz-empty">Nessuna esecuzione registrata in questo periodo.</p>
                    <?php else: ?>
                        <div class="viz-plot" role="img" aria-label="Esecuzioni registrate per giorno">
                            <?php foreach ([0, 50, 100] as $livello): ?>
                                <div class="viz-gridline" style="bottom: <?= $livello ?>%;"></div>
                                <div class="viz-tick" style="bottom: <?= $livello ?>%;"><?= (int) ($scala * $livello / 100) ?></div>
                            <?php endforeach; ?>
                            <div class="viz-cols">
                                <?php foreach ($serie as $i => $giorno): ?>
                                    <?php $ts = strtotime($giorno['data']); ?>
                                    <div class="viz-col viz-hit" tabindex="0"
                                         data-tip-title="<?= esc($giorniIt[(int) date('w', $ts)] . ' ' . date('d/m/Y', $ts), 'attr') ?>"
                                         data-tip="<?= esc(json_encode([
                                             [(string) $giorno['totale'], 'tentativi'],
                                             [(string) $giorno['passati'], 'passati'],
                                             [(string) $giorno['falliti'], 'falliti'],
                                             [(string) $giorno['altri'], 'bloccati o saltati'],
                                         ]), 'attr') ?>">
                                        <?php if ($i === $ultimo && $giorno['totale'] > 0): ?>
                                            <span class="viz-barlabel"><?= (int) $giorno['totale'] ?></span>
                                        <?php endif; ?>
                                        <?php if ($giorno['totale'] > 0): ?>
                                            <span class="viz-bar" style="height: <?= round($giorno['totale'] / $scala * 100, 2) ?>%;"></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="viz-xaxis">
                            <?php foreach ($serie as $giorno): ?>
                                <span><?= date('j', strtotime($giorno['data'])) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="viz-xcaption"><?= date('d/m', strtotime($serie[0]['data'])) ?> – <?= date('d/m', strtotime($serie[$ultimo]['data'])) ?></div>
                    <?php endif; ?>
                </div>

                <div data-viz-table hidden>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Giorno</th><th class="text-end">Tentativi</th><th class="text-end">Passati</th><th class="text-end">Falliti</th><th class="text-end">Altri</th></tr></thead>
                        <tbody>
                            <?php foreach (array_reverse($serie) as $giorno): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($giorno['data'])) ?></td>
                                    <td class="text-end"><?= (int) $giorno['totale'] ?></td>
                                    <td class="text-end"><?= (int) $giorno['passati'] ?></td>
                                    <td class="text-end"><?= (int) $giorno['falliti'] ?></td>
                                    <td class="text-end"><?= (int) $giorno['altri'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </figure>
        </div>
    </div>
<?php endif; ?>

<?php if ($statBug !== null): ?>
    <h6 class="viz-section">Bug</h6>
    <div class="row g-3 mb-3">
        <?= $tile('Bug da correggere', $num($statBug['aperti']), 'aperti, in lavorazione o riaperti') ?>
        <?= $tile('In attesa di riverifica', $num($statBug['da_riverificare']), 'risolti, da rieseguire') ?>
        <?= $tile('Bug chiusi', $num($statBug['chiusi']), 'riverifica superata') ?>
        <?php
        $giorni = $statBug['giorni_medi_chiusura'];
        $medio  = $giorni === null ? '—' : ($giorni < 1 ? number_format($giorni * 24, 1, ',', '.') . ' ore' : number_format($giorni, 1, ',', '.') . ' giorni');
        ?>
        <?= $tile('Tempo medio di chiusura', $medio, 'dalla segnalazione alla riverifica') ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <?= view('admin/dashboard/_barre', [
                'titolo'      => 'Bug per stato',
                'sottotitolo' => 'Tutti i bug registrati',
                'unita'       => 'bug',
                'righe'       => [
                    ['label' => 'Aperto', 'valore' => $statBug['per_stato']['aperto']],
                    ['label' => 'In lavorazione', 'valore' => $statBug['per_stato']['in_lavorazione']],
                    ['label' => 'Riaperto', 'valore' => $statBug['per_stato']['riaperto']],
                    ['label' => 'Risolto', 'valore' => $statBug['per_stato']['risolto']],
                    ['label' => 'Chiuso', 'valore' => $statBug['per_stato']['chiuso']],
                ],
            ], ['debug' => false]) ?>
        </div>
        <div class="col-lg-6">
            <?= view('admin/dashboard/_barre', [
                'titolo'      => 'Bug da correggere per gravità',
                'sottotitolo' => 'Aperti, in lavorazione o riaperti',
                'unita'       => 'bug',
                'righe'       => [
                    ['label' => 'Bassa', 'valore' => $statBug['aperti_per_gravita']['bassa'], 'colore' => 'var(--ord-1)'],
                    ['label' => 'Media', 'valore' => $statBug['aperti_per_gravita']['media'], 'colore' => 'var(--ord-2)'],
                    ['label' => 'Alta', 'valore' => $statBug['aperti_per_gravita']['alta'], 'colore' => 'var(--ord-3)'],
                    ['label' => 'Critica', 'valore' => $statBug['aperti_per_gravita']['critica'], 'colore' => 'var(--ord-4)'],
                ],
            ], ['debug' => false]) ?>
        </div>
    </div>
<?php endif; ?>

</div>
