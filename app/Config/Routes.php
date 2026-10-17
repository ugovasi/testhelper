<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static fn () => redirect()->to(session()->get('logged_in') ? site_url('admin/dashboard') : site_url('login')));

$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->post('logout', 'AuthController::logout');

$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'auth'], static function (RouteCollection $routes) {
    $routes->get('dashboard', 'DashboardController::index');

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

    $routes->group('log', ['filter' => 'permission:log.view'], static function (RouteCollection $routes) {
        $routes->get('/', 'LogController::index');
        $routes->post('datatable', 'LogController::datatable');
    });
});
