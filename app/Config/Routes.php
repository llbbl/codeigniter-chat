<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Auth routes
$routes->get('auth/register', 'Auth::register');
$routes->post('auth/processRegistration', 'Auth::processRegistration');
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/processLogin', 'Auth::processLogin');
$routes->get('auth/logout', 'Auth::logout');

// Chat routes
$routes->get('chat', 'Chat::index');
$routes->post('chat/update', 'Chat::update');
$routes->get('chat/backend', 'Chat::backend');
$routes->get('chat/json', 'Chat::json');
$routes->get('chat/jsonBackend', 'Chat::jsonBackend');
$routes->get('chat/html', 'Chat::html');
$routes->get('chat/htmlBackend', 'Chat::htmlBackend');

// CSP report route
$routes->post('csp-report', 'CspReport::index');
