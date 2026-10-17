<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Utenti<?= $this->endSection() ?>

<?php $permessiUtente = session()->get('permessi') ?? []; ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Elenco Utenti</h5>
        <?php if (in_array('utenti.create', $permessiUtente, true)): ?>
            <button type="button" id="btnNuovoUtente" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nuovo
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="tabellaUtenti" class="table table-striped table-bordered w-100">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Attivo</th>
                    <th class="text-center">Info</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalUtente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Utente</h5>
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
        tableSelector: '#tabellaUtenti',
        ajaxUrl: '<?= site_url('admin/utenti/datatable') ?>',
        modalId: 'modalUtente',
        createTrigger: '#btnNuovoUtente',
        createLabel: 'Nuovo utente',
        editLabel: 'Modifica utente',
        formUrlNew: '<?= site_url('admin/utenti/form') ?>',
        formUrlEdit: (id) => '<?= site_url('admin/utenti/form') ?>/' + id,
        storeUrl: '<?= site_url('admin/utenti/store') ?>',
        updateUrl: (id) => '<?= site_url('admin/utenti/update') ?>/' + id,
        deleteUrl: (id) => '<?= site_url('admin/utenti/delete') ?>/' + id,
        columns: [
            { data: 'id' },
            { data: 'nome', render: $.fn.dataTable.render.text() },
            { data: 'email', render: $.fn.dataTable.render.text() },
            {
                data: 'ruolo_nome',
                render: (data) => data ? $('<div>').text(data).html() : '<span class="text-muted">Nessuno</span>',
            },
            {
                data: 'attivo',
                render: (v) => v == 1
                    ? '<span class="badge text-bg-success">Si</span>'
                    : '<span class="badge text-bg-secondary">No</span>',
            },
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
