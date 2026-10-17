<?php
$mappa = ['bozza' => ['secondary', 'Bozza'], 'in_corso' => ['primary', 'In corso'], 'chiuso' => ['success', 'Chiuso']];
[$colore, $etichetta] = $mappa[$stato] ?? ['secondary', $stato];
?>
<span class="badge text-bg-<?= $colore ?>"><?= esc($etichetta) ?></span>
