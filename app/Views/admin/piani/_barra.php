<?php
$colore = $metriche['completo'] ? 'success' : ($metriche['a_rischio'] ? 'danger' : ($metriche['in_ritardo'] ? 'warning' : 'primary'));
?>
<div class="progress" style="min-width: 110px;" title="<?= (int) $metriche['chiusi'] ?> di <?= (int) $metriche['totale'] ?> passaggi chiusi">
    <div class="progress-bar bg-<?= $colore ?>" role="progressbar" style="width: <?= (float) $metriche['avanzamento_reale'] ?>%">
        <?= (float) $metriche['avanzamento_reale'] ?>%
    </div>
</div>
<?php if ($metriche['in_ritardo'] && !$metriche['completo']): ?>
    <small class="text-danger">in ritardo di <?= (float) $metriche['ritardo_giorni'] ?> gg</small>
<?php endif; ?>
