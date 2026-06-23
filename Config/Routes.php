<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('declarations', [
    'namespace' => 'App\Modules\Declarations\Controllers\Admin',
], static function (RouteCollection $routes): void {
    $routes->get('persons', 'PersonsController::index');
    $routes->post('persons/create', 'PersonsController::create');
    $routes->match(['get', 'post'], 'persons/datatable', 'PersonsController::datatable');
    $routes->get('persons/(:num)', 'PersonsController::show/$1');
    $routes->post('persons/(:num)/relations/create', 'PersonsController::createRelation/$1');
    $routes->post('persons/(:num)/relations/(:num)/packets/create', 'PersonsController::createPacket/$1/$2');
    $routes->get('packets/(:num)', 'PacketsController::show/$1');
    $routes->post('packets/(:num)/invitation/create', 'PacketsController::createInvitation/$1');
    $routes->post('persons/(:num)/update', 'PersonsController::update/$1');
    $routes->post('packets/(:num)/items/(:num)/accept', 'PacketsController::acceptItem/$1/$2');
    $routes->post('packets/(:num)/items/(:num)/reject', 'PacketsController::rejectItem/$1/$2');
    $routes->post('packets/(:num)/invitation/send-new-link', 'PacketsController::sendNewInvitationLink/$1');
    $routes->post('packets/(:num)/close', 'PacketsController::closePacket/$1');
    $routes->post('packets/(:num)/items/(:num)/reopen-for-correction', 'PacketsController::reopenItemForCorrection/$1/$2');
});

$routes->group('', [
    'hostname' => 'nyilatkozatok2.miellgroup.com',
    'namespace' => 'App\Modules\Declarations\Controllers\Public',
], static function ($routes): void {
    $routes->get('/', 'InvitationController::landing');
    $routes->get('start/(:segment)', 'InvitationController::start/$1');
    $routes->get('start/(:segment)/item/(:num)', 'InvitationController::item/$1/$2');
    $routes->post('start/(:segment)/tax-template/(:num)/select', 'InvitationController::selectTaxTemplate/$1/$2');
    $routes->post('start/(:segment)/item/(:num)', 'InvitationController::submitItem/$1/$2');
});

$routes->group('my-declarations', [
    'namespace' => 'App\Modules\Declarations\Controllers\Employee',
], static function (RouteCollection $routes): void {
    $routes->get('/', 'MyDeclarationsController::index');
});