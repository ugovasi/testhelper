<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static fn () => redirect()->to(session()->get('logged_in') ? site_url('admin/dashboard') : site_url('login')));

$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->post('logout', 'AuthController::logout');

$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('guida', 'GuidaController::index');

    $routes->group('utenti', ['filter' => 'permission:utenti.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'UtentiController::index');
        $routes->post('datatable', 'UtentiController::datatable');
        $routes->get('form', 'UtentiController::form', ['filter' => 'permission:utenti.create']);
        $routes->get('form/(:num)', 'UtentiController::form/$1', ['filter' => 'permission:utenti.edit']);
        $routes->post('store', 'UtentiController::store', ['filter' => 'permission:utenti.create']);
        $routes->post('update/(:num)', 'UtentiController::update/$1', ['filter' => 'permission:utenti.edit']);
        $routes->delete('delete/(:num)', 'UtentiController::delete/$1', ['filter' => 'permission:utenti.delete']);
    });

    $routes->group('ruoli', ['filter' => 'permission:ruoli.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'RuoliController::index');
        $routes->post('datatable', 'RuoliController::datatable');
        $routes->get('form', 'RuoliController::form', ['filter' => 'permission:ruoli.create']);
        $routes->get('form/(:num)', 'RuoliController::form/$1', ['filter' => 'permission:ruoli.edit']);
        $routes->post('store', 'RuoliController::store', ['filter' => 'permission:ruoli.create']);
        $routes->post('update/(:num)', 'RuoliController::update/$1', ['filter' => 'permission:ruoli.edit']);
        $routes->delete('delete/(:num)', 'RuoliController::delete/$1', ['filter' => 'permission:ruoli.delete']);
    });

    $routes->group('permessi', ['filter' => 'permission:permessi.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'PermessiController::index');
        $routes->post('datatable', 'PermessiController::datatable');
        $routes->get('form', 'PermessiController::form', ['filter' => 'permission:permessi.create']);
        $routes->get('form/(:num)', 'PermessiController::form/$1', ['filter' => 'permission:permessi.edit']);
        $routes->post('store', 'PermessiController::store', ['filter' => 'permission:permessi.create']);
        $routes->post('update/(:num)', 'PermessiController::update/$1', ['filter' => 'permission:permessi.edit']);
        $routes->delete('delete/(:num)', 'PermessiController::delete/$1', ['filter' => 'permission:permessi.delete']);
    });

    // Moduli CRUD della gestione test: stesso schema di utenti/ruoli/permessi,
    // ripetuto qui in un'unica closure perche' e' identico per tutti.
    $crud = static function (RouteCollection $routes, string $slug, string $controller, string $permesso, ?string $viewPermesso = null) {
        $routes->group($slug, ['filter' => 'permission:' . ($viewPermesso ?? $permesso) . '.view'], static function (RouteCollection $routes) use ($controller, $permesso) {
            $routes->get('/', "{$controller}::index");
            $routes->post('datatable', "{$controller}::datatable");
            $routes->get('form', "{$controller}::form", ['filter' => "permission:{$permesso}.create"]);
            $routes->get('form/(:num)', "{$controller}::form/$1", ['filter' => "permission:{$permesso}.edit"]);
            $routes->post('store', "{$controller}::store", ['filter' => "permission:{$permesso}.create"]);
            $routes->post('update/(:num)', "{$controller}::update/$1", ['filter' => "permission:{$permesso}.edit"]);
            $routes->delete('delete/(:num)', "{$controller}::delete/$1", ['filter' => "permission:{$permesso}.delete"]);
        });
    };

    $crud($routes, 'prodotti', 'ProdottiController', 'prodotti');
    $crud($routes, 'versioni', 'VersioniController', 'prodotti');
    $crud($routes, 'suite', 'SuiteController', 'casi');
    $crud($routes, 'casi', 'CasiTestController', 'casi');
    $crud($routes, 'piani', 'PianiTestController', 'piani');

    $routes->get('casi/dettaglio/(:num)', 'CasiTestController::dettaglio/$1', ['filter' => 'permission:casi.view']);
    $routes->get('piani/report/(:num)', 'PianiTestController::report/$1', ['filter' => 'permission:piani.view']);
    $routes->get('piani/scheda/(:num)', 'PianiTestController::scheda/$1', ['filter' => 'permission:piani.view']);
    $routes->get('piani/dettaglio/(:num)', 'PianiTestController::dettaglio/$1', ['filter' => 'permission:piani.view']);
    $routes->post('piani/chiudi/(:num)', 'PianiTestController::chiudi/$1', ['filter' => 'permission:esecuzioni.create']);

    $routes->group('esecuzioni', ['filter' => 'permission:esecuzioni.view'], static function (RouteCollection $routes) {
        $routes->get('caso/(:num)', 'EsecuzioniController::caso/$1');
        $routes->get('riverifiche', 'EsecuzioniController::riverifiche');
        $routes->post('registra', 'EsecuzioniController::registra', ['filter' => 'permission:esecuzioni.create']);
        $routes->post('modifica/(:num)', 'EsecuzioniController::modifica/$1', ['filter' => 'permission:esecuzioni.create']);
    });

    // I bug non si creano ne si eliminano a mano: nascono da un passaggio fallito.
    $routes->group('bug', ['filter' => 'permission:bug.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'BugController::index');
        $routes->post('datatable', 'BugController::datatable');
        $routes->get('form/(:num)', 'BugController::form/$1');
        $routes->post('update/(:num)', 'BugController::update/$1', ['filter' => 'permission:bug.update']);
        // Allegare e togliere allegati: il controller ammette bug.update oppure esecuzioni.create.
        $routes->post('allega/(:num)', 'BugController::allega/$1');
        $routes->post('elimina-allegato/(:num)', 'BugController::eliminaAllegato/$1');
        $routes->get('allegato/(:num)', 'BugController::allegato/$1');
    });

    $routes->group('log', ['filter' => 'permission:log.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'LogController::index');
        $routes->post('datatable', 'LogController::datatable');
    });
});
