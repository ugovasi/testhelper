<?php
$permessiUtente = session()->get('permessi') ?? [];
$menuItems = [
    ['label' => 'Dashboard', 'icon' => 'bi bi-speedometer2', 'url' => 'admin/dashboard', 'permesso' => null],
    ['label' => 'Test', 'icon' => 'bi bi-clipboard-check', 'children' => [
        ['label' => 'Piani di test',  'icon' => 'bi bi-calendar-check', 'url' => 'admin/piani',            'permesso' => 'piani.view'],
        ['label' => 'Riverifiche',    'icon' => 'bi bi-arrow-repeat',   'url' => 'admin/esecuzioni/riverifiche', 'permesso' => 'esecuzioni.view'],
        ['label' => 'Bug',            'icon' => 'bi bi-bug',            'url' => 'admin/bug',              'permesso' => 'bug.view'],
    ]],
    ['label' => 'Libreria', 'icon' => 'bi bi-collection', 'children' => [
        ['label' => 'Prodotti',       'icon' => 'bi bi-box-seam',       'url' => 'admin/prodotti',         'permesso' => 'prodotti.view'],
        ['label' => 'Versioni',       'icon' => 'bi bi-tag',            'url' => 'admin/versioni',         'permesso' => 'prodotti.view'],
        ['label' => 'Suite',          'icon' => 'bi bi-folder2-open',   'url' => 'admin/suite',            'permesso' => 'casi.view'],
        ['label' => 'Casi di test',   'icon' => 'bi bi-list-check',     'url' => 'admin/casi',             'permesso' => 'casi.view'],
    ]],
    ['label' => 'Admin', 'icon' => 'bi bi-gear', 'children' => [
        ['label' => 'Utenti',    'icon' => 'bi bi-people',       'url' => 'admin/utenti',    'permesso' => 'utenti.view'],
        ['label' => 'Ruoli',     'icon' => 'bi bi-shield-lock',  'url' => 'admin/ruoli',     'permesso' => 'ruoli.view'],
        ['label' => 'Permessi',  'icon' => 'bi bi-key',          'url' => 'admin/permessi',  'permesso' => 'permessi.view'],
        ['label' => 'Registro attivita', 'icon' => 'bi bi-clock-history', 'url' => 'admin/log', 'permesso' => 'log.view'],
    ]],
    ['label' => 'Guida', 'icon' => 'bi bi-question-circle', 'url' => 'admin/guida', 'permesso' => null],
];
$puoVedere = static fn (array $item): bool => $item['permesso'] === null || in_array($item['permesso'], $permessiUtente, true);
$currentPath = uri_string();
?>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="<?= site_url('admin/dashboard') ?>" class="brand-link">
            <i class="bi bi-shield-check fs-4 ms-2"></i>
            <span class="brand-text fw-light ms-2">TestHelper</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (isset($item['children'])): ?>
                        <?php
                        $figli  = array_filter($item['children'], $puoVedere);
                        $aperto = false;
                        foreach ($figli as $figlio) {
                            $aperto = $aperto || str_starts_with($currentPath, $figlio['url']);
                        }
                        ?>
                        <?php if ($figli !== []): ?>
                            <li class="nav-item <?= $aperto ? 'menu-open' : '' ?>">
                                <a href="#" class="nav-link <?= $aperto ? 'active' : '' ?>">
                                    <i class="nav-icon <?= esc($item['icon']) ?>"></i>
                                    <p>
                                        <?= esc($item['label']) ?>
                                        <i class="nav-arrow bi bi-chevron-right"></i>
                                    </p>
                                </a>
                                <ul class="nav nav-treeview">
                                    <?php foreach ($figli as $figlio): ?>
                                        <li class="nav-item">
                                            <a href="<?= site_url($figlio['url']) ?>"
                                               class="nav-link <?= str_starts_with($currentPath, $figlio['url']) ? 'active' : '' ?>">
                                                <i class="nav-icon <?= esc($figlio['icon']) ?>"></i>
                                                <p><?= esc($figlio['label']) ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php elseif ($puoVedere($item)): ?>
                        <li class="nav-item">
                            <a href="<?= site_url($item['url']) ?>"
                               class="nav-link <?= str_starts_with($currentPath, $item['url']) ? 'active' : '' ?>">
                                <i class="nav-icon <?= esc($item['icon']) ?>"></i>
                                <p><?= esc($item['label']) ?></p>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</aside>
