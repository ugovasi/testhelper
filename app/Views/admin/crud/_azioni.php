<div class="btn-group btn-group-sm">
    <?= $extra ?>
    <?php if ($puoModificare): ?>
        <button type="button" class="btn btn-outline-primary" data-crud-action="edit" data-id="<?= $id ?>" title="<?= esc($titoloModifica, 'attr') ?>">
            <i class="bi bi-pencil"></i>
        </button>
    <?php endif; ?>
    <?php if ($puoEliminare): ?>
        <button type="button" class="btn btn-outline-danger" data-crud-action="delete" data-id="<?= $id ?>" data-label="<?= esc($etichettaRiga, 'attr') ?>" title="Elimina">
            <i class="bi bi-trash"></i>
        </button>
    <?php endif; ?>
</div>
