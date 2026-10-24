<?php
/**
 * Report stampabile di un piano di test. Pagina autonoma (senza menu e barre) pensata per
 * la stampa o per "Salva come PDF" del browser.
 */
$data    = static fn (?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$dataOra = static fn (?string $d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
$minuti  = static fn (?int $m) => $m === null ? '—' : $m . ' min';

$statoPassaggio = [
    'da_eseguire' => 'Da eseguire', 'chiuso' => 'Chiuso', 'fallito' => 'Fallito',
    'da_riverificare' => 'Da riverificare', 'bloccato' => 'Bloccato', 'saltato' => 'Saltato',
];
$statoCaso = ['da_iniziare' => 'Da iniziare', 'in_corso' => 'In corso', 'con_bug' => 'Con bug', 'completato' => 'Completato'];
$statoPiano = ['bozza' => 'Bozza', 'in_corso' => 'In corso', 'chiuso' => 'Chiuso'];
$etichetta = static fn (string $v) => ucfirst(str_replace('_', ' ', $v));

$tentativi = array_sum(array_map(static fn ($c) => array_sum(array_map(static fn ($p) => count($p['esecuzioni']), $c['passaggi'])), $casi));
$bugAperti = count(array_filter($bug, static fn ($b) => in_array($b['stato'], ['aperto', 'in_lavorazione', 'riaperto'], true)));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-size: .85rem; background: #fff; color: #111; }
        .report { max-width: 1000px; margin: 0 auto; padding: 1rem; }
        h1 { font-size: 1.5rem; }
        h2 { font-size: 1.1rem; margin-top: 1.5rem; border-bottom: 1px solid #999; padding-bottom: .2rem; }
        h3 { font-size: .95rem; margin: 1rem 0 .3rem; }
        table { width: 100%; }
        th { background: #f1f1f1; }
        td, th { vertical-align: top; }
        .caso { break-inside: auto; }
        tr, .riepilogo div { break-inside: avoid; }
        thead { display: table-header-group; }
        .kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; }
        .kpi div { border: 1px solid #ccc; padding: .4rem .6rem; border-radius: .25rem; }
        .kpi strong { display: block; font-size: 1.15rem; }
        .piccolo { font-size: .75rem; color: #555; }
        @media print {
            .no-print { display: none !important; }
            .report { max-width: none; padding: 0; }
            a { color: inherit; text-decoration: none; }
            h2 { break-after: avoid; }
        }
        @page { margin: 1.5cm; }
    </style>
</head>
<body>
<div class="report">
    <div class="no-print d-flex gap-2 mb-3">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Stampa / Salva come PDF</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/piani/dettaglio/' . $piano['id']) ?>"><i class="bi bi-arrow-left"></i> Torna al piano</a>
    </div>

    <h1>Report del piano di test</h1>
    <div class="piccolo mb-2">Generato il <?= date('d/m/Y H:i') ?> da <?= esc($generatoDa) ?> · TestHelper</div>

    <h2>Dati del piano</h2>
    <table class="table table-sm table-borderless mb-0">
        <tr><th style="width: 22%;">Piano</th><td><?= esc($piano['nome']) ?></td><th style="width: 22%;">Stato</th><td><?= esc($statoPiano[$piano['stato']] ?? $piano['stato']) ?></td></tr>
        <tr><th>Prodotto e versione</th><td><?= esc($piano['prodotto_nome'] . ' ' . $piano['versione_nome']) ?></td><th>Tester</th><td><?= esc($piano['assegnatario_nome'] ?? 'non assegnato') ?></td></tr>
        <tr><th>Inizio</th><td><?= $data($piano['data_inizio']) ?></td><th>Fine prevista</th><td><?= $data($piano['data_fine_prevista']) ?></td></tr>
        <tr><th>Fine effettiva</th><td><?= $data($piano['data_fine_effettiva']) ?></td><th>Extra riverifiche</th><td><?= (int) $piano['quota_riverifica'] ?>%</td></tr>
        <tr><th>Creato</th><td colspan="3"><?= $dataOra($piano['created_at']) ?> da <?= esc($piano['creato_da_nome'] ?? 'Sistema') ?></td></tr>
    </table>

    <h2>Riepilogo</h2>
    <div class="kpi riepilogo">
        <div><strong><?= $metriche['avanzamento_reale'] ?>%</strong> avanzamento (<?= (int) $metriche['chiusi'] ?>/<?= (int) $metriche['totale'] ?> passaggi chiusi)</div>
        <div><strong><?= $metriche['avanzamento_atteso'] ?>%</strong> atteso a oggi (giorno <?= (int) $metriche['giorni_trascorsi'] ?>/<?= (int) $metriche['giorni_totali'] ?>)</div>
        <div><strong><?= $piano['stato'] === 'chiuso' ? 'Nessuno' : ($metriche['in_ritardo'] ? $metriche['ritardo_giorni'] . ' gg' : 'Nei tempi') ?></strong> ritardo</div>
        <div><strong><?= $data($metriche['proiezione_fine']) ?></strong> fine <?= $piano['stato'] === 'chiuso' ? 'effettiva' : 'proiettata' ?><?= $metriche['a_rischio'] ? ' (a rischio)' : '' ?></div>
        <div><strong><?= (int) $metriche['stima_minuti'] ?> min</strong> tempo stimato (con extra)</div>
        <div>
            <strong><?= $minuti($metriche['minuti_effettivi']) ?></strong> tempo impiegato
            <?php if ($metriche['scostamento_pct'] !== null): ?>(<?= ($metriche['scostamento_pct'] > 0 ? '+' : '') . $metriche['scostamento_pct'] ?>% sulla stima)<?php endif; ?>
        </div>
        <div><strong><?= $tentativi ?></strong> tentativi registrati</div>
        <div><strong><?= count($bug) ?></strong> bug (<?= $bugAperti ?> da correggere)</div>
    </div>
    <p class="piccolo mt-2 mb-0">
        Passaggi: <?= (int) $metriche['chiusi'] ?> chiusi · <?= (int) $metriche['da_riverificare'] ?> da riverificare · <?= (int) $metriche['fermi'] ?> fermi (falliti o bloccati) ·
        <?= (int) $metriche['da_eseguire'] ?> da eseguire · <?= (int) $metriche['saltati'] ?> saltati.
    </p>

    <h2>Dettaglio dei casi e dei passaggi</h2>
    <?php foreach ($casi as $caso): ?>
        <div class="caso">
            <h3>
                <?= esc($caso['titolo']) ?>
                <span class="piccolo fw-normal">· <?= esc($caso['suite_nome']) ?> · priorità <?= esc($caso['priorita']) ?> · <?= esc($statoCaso[$caso['stato']]) ?> ·
                    stima <?= (int) $caso['stima_minuti'] ?> min · impiegati <?= $minuti($caso['minuti_effettivi']) ?></span>
            </h3>
            <table class="table table-sm table-bordered mb-2">
                <thead>
                    <tr>
                        <th style="width: 2rem;">#</th><th style="width: 24%;">Azione</th><th style="width: 18%;">Risultato atteso</th>
                        <th style="width: 6rem;">Stato</th><th>Tentativi</th><th style="width: 17%;">Bug</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($caso['passaggi'] as $p): ?>
                        <tr>
                            <td><?= (int) $p['ordine'] ?></td>
                            <td><?= nl2br(esc($p['azione'])) ?><div class="piccolo">stima <?= (int) $p['stima_minuti'] ?> min</div></td>
                            <td><?= $p['risultato_atteso'] ? nl2br(esc($p['risultato_atteso'])) : '—' ?></td>
                            <td><strong><?= esc($statoPassaggio[$p['stato']]) ?></strong><?php if ($p['minuti_effettivi'] !== null): ?><div class="piccolo"><?= (int) $p['minuti_effettivi'] ?> min</div><?php endif; ?></td>
                            <td>
                                <?php foreach ($p['esecuzioni'] as $e): ?>
                                    <div>
                                        <strong>#<?= (int) $e['tentativo'] ?> <?= esc($e['esito']) ?></strong>
                                        <span class="piccolo">
                                            <?= $dataOra($e['eseguito_il']) ?>
                                            <?php if ($e['eseguito_da_nome']): ?> · <?= esc($e['eseguito_da_nome']) ?><?php endif; ?>
                                            <?php if ($e['minuti_impiegati'] !== null): ?> · <?= (int) $e['minuti_impiegati'] ?> min<?php endif; ?>
                                        </span>
                                        <?php if ($e['note']): ?><div class="piccolo">Note: <?= esc($e['note']) ?></div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($p['esecuzioni'] === []): ?>Non eseguito<?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['bug']): ?>
                                    #<?= (int) $p['bug']['id'] ?> <?= esc($p['bug']['titolo']) ?>
                                    <div class="piccolo"><?= esc($etichetta($p['bug']['stato'])) ?> · <?= esc($p['bug']['gravita']) ?></div>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    <?php if ($casi === []): ?><p>Il piano non contiene casi.</p><?php endif; ?>

    <h2>Bug del piano</h2>
    <?php if ($bug === []): ?>
        <p>Nessun bug segnalato.</p>
    <?php else: ?>
        <table class="table table-sm table-bordered">
            <thead><tr><th>#</th><th>Titolo</th><th>Origine</th><th>Gravità</th><th>Stato</th><th>Assegnato a</th><th>Aperto</th><th>Chiuso</th></tr></thead>
            <tbody>
                <?php foreach ($bug as $b): ?>
                    <tr>
                        <td><?= (int) $b['id'] ?></td>
                        <td>
                            <?= esc($b['titolo']) ?>
                            <?php if ($b['note_risoluzione']): ?><div class="piccolo">Correzione: <?= esc($b['note_risoluzione']) ?></div><?php endif; ?>
                        </td>
                        <td><?= esc($b['caso_titolo']) ?>, passaggio <?= (int) $b['passaggio_ordine'] ?></td>
                        <td><?= esc($b['gravita']) ?></td>
                        <td><?= esc($etichetta($b['stato'])) ?></td>
                        <td><?= esc($b['assegnato_nome'] ?? '—') ?></td>
                        <td><?= $dataOra($b['created_at']) ?></td>
                        <td><?= $dataOra($b['chiuso_il']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
