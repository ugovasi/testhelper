<?= $this->extend('layouts/admin') ?>

<?= $this->section('page_title') ?>Registro attivita<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Registro attivita</h5>
    </div>
    <div class="card-body">
        <table id="tabellaLog" class="table table-striped table-bordered w-100">
            <thead>
                <tr>
                    <th>Data/ora</th>
                    <th>Utente</th>
                    <th>Azione</th>
                    <th>Modulo</th>
                    <th>Descrizione</th>
                    <th>Esito</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    // Sola lettura: niente modali/CRUD, quindi non usiamo AdminCRUD.initModule
    // (richiede un modal di creazione/modifica) ma solo il suo helper per gli
    // header CSRF, con una DataTable server-side inizializzata direttamente.
    $('#tabellaLog').DataTable({
        processing: true,
        serverSide: true,
        ajax: (data, callback) => {
            fetch('<?= site_url('admin/log/datatable') ?>', {
                method: 'POST',
                headers: AdminCRUD.csrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
                body: $.param(data),
            })
                .then((res) => res.json())
                .then(callback);
        },
        columns: [
            { data: 'created_at' },
            {
                data: null,
                render: (row) => $('<div>').text(row.utente_nome || row.utente_email || 'Sistema').html(),
            },
            { data: 'azione', render: $.fn.dataTable.render.text() },
            { data: 'modulo', render: $.fn.dataTable.render.text() },
            { data: 'descrizione', render: $.fn.dataTable.render.text(), defaultContent: '' },
            {
                data: 'esito',
                render: (esito) => (esito == 1
                    ? '<span class="badge text-bg-success">OK</span>'
                    : '<span class="badge text-bg-danger">Fallito</span>'),
            },
            { data: 'ip_address', render: $.fn.dataTable.render.text(), defaultContent: '' },
        ],
        order: [[0, 'desc']],
        language: { url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/it-IT.json' },
    });
});
</script>
<?= $this->endSection() ?>
