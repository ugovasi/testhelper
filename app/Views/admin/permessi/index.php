<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Permessi<?= $this->endSection() ?>

<?php $permessiUtente = session()->get('permessi') ?? []; ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Elenco Permessi</h5>
        <?php if (in_array('permessi.create', $permessiUtente, true)): ?>
            <button type="button" id="btnNuovoPermesso" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nuovo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tabellaPermessi" class="table table-striped table-bordered w-100">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Chiave</th>
                    <th>Descrizione</th>
                    <th class="text-center">Info</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalPermesso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Permesso</h5>
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
        tableSelector: '#tabellaPermessi',
        ajaxUrl: '<?= site_url('admin/permessi/datatable') ?>',
        modalId: 'modalPermesso',
        createTrigger: '#btnNuovoPermesso',
        createLabel: 'Nuovo permesso',
        editLabel: 'Modifica permesso',
        formUrlNew: '<?= site_url('admin/permessi/form') ?>',
        formUrlEdit: (id) => '<?= site_url('admin/permessi/form') ?>/' + id,
        storeUrl: '<?= site_url('admin/permessi/store') ?>',
        updateUrl: (id) => '<?= site_url('admin/permessi/update') ?>/' + id,
        deleteUrl: (id) => '<?= site_url('admin/permessi/delete') ?>/' + id,
        columns: [
            { data: 'id' },
            { data: 'chiave', render: $.fn.dataTable.render.text() },
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
