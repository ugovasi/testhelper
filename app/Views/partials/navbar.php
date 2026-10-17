<nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
                    <i class="bi bi-person-circle"></i>
                    <?= esc(session()->get('nome')) ?>
                    <span class="badge text-bg-secondary"><?= esc(session()->get('ruolo_nome') ?? 'Nessun ruolo') ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <form action="<?= site_url('logout') ?>" method="post" class="px-3 py-1">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-box-arrow-right"></i> Esci
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>
