<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    $routes->get('example', 'Example::index');
    $routes->get('filters', 'Filters::index');
    $routes->get('autocomplete', 'Autocomplete::index');
    $routes->get('prompt', 'Prompt::index');
});
