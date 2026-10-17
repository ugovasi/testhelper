<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Ruoli<?= $this->endSection() ?>

<?php $permessiUtente = session()->get('permessi') ?? []; ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Elenco Ruoli</h5>
        <?php if (in_array('ruoli.create', $permessiUtente, true)): ?>
            <button type="button" id="btnNuovoRuolo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nuovo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tabellaRuoli" class="table table-striped table-bordered w-100">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Descrizione</th>
                    <th class="text-center">Info</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalRuolo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ruolo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    AdminCRUD.initModule({
        tableSelector: '#tabellaRuoli',
        ajaxUrl: '<?= site_url('admin/ruoli/datatable') ?>',
        modalId: 'modalRuolo',
        createTrigger: '#btnNuovoRuolo',
        createLabel: 'Nuovo ruolo',
        editLabel: 'Modifica ruolo',
        formUrlNew: '<?= site_url('admin/ruoli/form') ?>',
        formUrlEdit: (id) => '<?= site_url('admin/ruoli/form') ?>/' + id,
        storeUrl: '<?= site_url('admin/ruoli/store') ?>',
        updateUrl: (id) => '<?= site_url('admin/ruoli/update') ?>/' + id,
        deleteUrl: (id) => '<?= site_url('admin/ruoli/delete') ?>/' + id,
        columns: [
            { data: 'id' },
            { data: 'nome', render: $.fn.dataTable.render.text() },
            { data: 'descrizione', render: $.fn.dataTable.render.text(), defaultContent: '' },
            {
                data: null, orderable: false, searchable: false, className: 'text-center',
                render: (row) => AdminCRUD.infoPopoverHtml(row),
            },
            { data: 'azioni', orderable: false, searchable: false },
        ],
        order: [[0, 'desc']],
    });
});
</script>
<?= $this->endSection() ?>
