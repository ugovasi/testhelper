<?php
$permessiUtente = session()->get('permessi') ?? [];
$menuItems = [
    ['label' => 'Dashboard', 'icon' => 'bi bi-speedometer2', 'url' => 'admin/dashboard', 'permesso' => null],
    ['label' => 'Utenti',    'icon' => 'bi bi-people',       'url' => 'admin/utenti',    'permesso' => 'utenti.view'],
    ['label' => 'Ruoli',     'icon' => 'bi bi-shield-lock',  'url' => 'admin/ruoli',     'permesso' => 'ruoli.view'],
    ['label' => 'Permessi',  'icon' => 'bi bi-key',          'url' => 'admin/permessi',  'permesso' => 'permessi.view'],
    ['label' => 'Registro attivita', 'icon' => 'bi bi-clock-history', 'url' => 'admin/log', 'permesso' => 'log.view'],
];
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
                    <?php if ($item['permesso'] === null || in_array($item['permesso'], $permessiUtente, true)): ?>
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
