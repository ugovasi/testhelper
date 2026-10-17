<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= $this->renderSection('page_title') ?> - TestHelper</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css">

    <style>
        /* Voci dei sottomenu rientrate rispetto alla sezione che le contiene. */
        .app-sidebar .nav-treeview > .nav-item > .nav-link { padding-left: 2rem; }

        /* Layout compatto: meno spazio verticale e font leggermente ridotto. */
        body { font-size: .875rem; }
        .app-header .nav-link { padding-top: .35rem; padding-bottom: .35rem; }
        .app-content-header { padding: .5rem 0; }
        .app-content-header h3 { font-size: 1.25rem; }
        .app-content { padding-bottom: .5rem; }
        .app-sidebar .sidebar-brand { height: 2.9rem; }
        .app-sidebar .nav-link { padding-top: .35rem; padding-bottom: .35rem; }
        .card { margin-bottom: .75rem; }
        .card-header { padding: .5rem .75rem; }
        .card-header h5, .card-header h6 { font-size: 1rem; }
        .card-body { padding: .75rem; }
        .table > :not(caption) > * > * { padding: .3rem .5rem; vertical-align: middle; }
        .form-label { margin-bottom: .2rem; }
        .form-control, .form-select { padding-top: .3rem; padding-bottom: .3rem; font-size: .875rem; }
        .modal-header, .modal-footer { padding: .6rem .9rem; }
        .modal-body { padding: .9rem; }
        .alert { padding: .5rem .75rem; margin-bottom: .6rem; }
        .app-footer { padding: .4rem 1rem; }
        div.dt-container .dt-layout-row { margin: .25rem 0; }
        div.dt-container .dt-search input, div.dt-container .dt-length select { padding: .15rem .4rem; }
    </style>

    <?= $this->renderSection('styles') ?>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <?= $this->include('partials/navbar') ?>
    <?= $this->include('partials/sidebar') ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h3 class="mb-0"><?= $this->renderSection('page_title') ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                <div id="alert-container">
                    <?php if (session()->getFlashdata('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= esc(session()->getFlashdata('success')) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= esc(session()->getFlashdata('error')) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>

                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">v1.0.0</div>
        <strong>&copy; <?= date('Y') ?></strong> Applicativo demo Auth + RBAC.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= base_url('assets/js/admin-crud.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
