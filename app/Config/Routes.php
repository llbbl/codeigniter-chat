<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Chat routes
$routes->get('chat', 'Chat::index');
$routes->post('chat/update', 'Chat::update');
$routes->get('chat/backend', 'Chat::backend');
$routes->get('chat/json', 'Chat::json');
$routes->get('chat/json_backend', 'Chat::json_backend');
$routes->get('chat/html', 'Chat::html');
$routes->get('chat/html_backend', 'Chat::html_backend');
