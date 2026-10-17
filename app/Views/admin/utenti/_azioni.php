<div class="btn-group btn-group-sm">
    <?php if ($puoModificare): ?>
        <button type="button" class="btn btn-outline-primary" data-crud-action="edit" data-id="<?= (int) $utente['id'] ?>" title="Modifica">
            <i class="bi bi-pencil"></i>
        </button>
    <?php endif; ?>
    <?php if ($puoEliminare): ?>
        <button type="button" class="btn btn-outline-danger" data-crud-action="delete" data-id="<?= (int) $utente['id'] ?>" data-label="<?= esc($utente['nome'], 'attr') ?>" title="Elimina">
            <i class="bi bi-trash"></i>
        </button>
    <?php endif; ?>
</div>
