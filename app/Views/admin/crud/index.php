<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?php
$permessiUtente = session()->get('permessi') ?? [];
$slug           = $crud['slug'];
$idBase         = ucfirst(str_replace('-', '', $slug));
?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Elenco <?= esc($title) ?></h5>
        <?php if (in_array($crud['permesso'] . '.create', $permessiUtente, true)): ?>
            <button type="button" id="btnNuovo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nuovo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabella<?= esc($idBase, 'attr') ?>" class="table table-striped table-bordered w-100">
                <thead>
                    <tr>
                        <?php foreach ($crud['colonne'] as $colonna): ?>
                            <th><?= esc($colonna['label']) ?></th>
                        <?php endforeach; ?>
                        <th class="text-center">Info</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal<?= esc($idBase, 'attr') ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= esc(ucfirst($crud['etichetta'])) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php foreach ($crud['script'] as $script): ?>
<script src="<?= base_url($script) ?>"></script>
<?php endforeach; ?>
<script>
$(function () {
    const base = <?= json_encode(site_url('admin/' . $slug)) ?>;
    const colonne = <?= json_encode(array_map(static fn ($c) => [
        'data' => $c['data'], 'html' => $c['html'] ?? false, 'ordina' => ($c['ordina'] ?? null) !== null,
    ], $crud['colonne'])) ?>.map((c) => ({
        data: c.data,
        orderable: c.ordina,
        defaultContent: '',
        render: c.html ? undefined : $.fn.dataTable.render.text(),
    }));

    AdminCRUD.initModule({
        tableSelector: '#tabella<?= esc($idBase, 'js') ?>',
        ajaxUrl: base + '/datatable',
        modalId: 'modal<?= esc($idBase, 'js') ?>',
        createTrigger: '#btnNuovo',
        createLabel: 'Nuov<?= $crud['genere'] ?> <?= esc($crud['etichetta'], 'js') ?>',
        editLabel: 'Modifica <?= esc($crud['etichetta'], 'js') ?>',
        formUrlNew: base + '/form',
        formUrlEdit: (id) => base + '/form/' + id,
        storeUrl: base + '/store',
        updateUrl: (id) => base + '/update/' + id,
        deleteUrl: (id) => base + '/delete/' + id,
        columns: colonne.concat([
            {
                data: null, orderable: false, searchable: false, className: 'text-center',
                render: (row) => AdminCRUD.infoPopoverHtml(row),
            },
            { data: 'azioni', orderable: false, searchable: false },
        ]),
        order: [[0, 'desc']],
    });
});
</script>
<?= $this->endSection() ?>
