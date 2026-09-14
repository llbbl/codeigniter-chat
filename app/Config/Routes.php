<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Auth routes
$routes->get('auth/register', 'Auth::register');
$routes->post('auth/processRegistration', 'Auth::processRegistration', ['filter' => ['rate:auth', 'validate:registration']]);
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/processLogin', 'Auth::processLogin', ['filter' => ['rate:auth', 'validate:login']]);
$routes->get('auth/logout', 'Auth::logout');

// Chat routes
$routes->get('chat', 'Chat::index');
$routes->post('chat/update', 'Chat::update', ['filter' => ['rate:write', 'validate:message']]);
$routes->get('chat/backend', 'Chat::backend');
$routes->get('chat/json', 'Chat::json');
$routes->get('chat/html', 'Chat::html');
$routes->get('chat/htmlBackend', 'Chat::htmlBackend');
$routes->get('chat/vue', 'Chat::vue');
$routes->get('chat/svelte', 'Chat::svelte');

// Stable, machine-consumed API routes.
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('messages', 'MessagesController::list', ['filter' => ['apiFormat', 'auth']]);
    $routes->get('messages/search', 'MessagesController::search', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('messages', 'MessagesController::create', ['filter' => ['apiFormat', 'auth', 'rate:write', 'validate:message']]);
    $routes->get('messages/xml', 'MessagesController::listXml', ['filter' => 'auth']);
    $routes->post('push-subscriptions', 'PushSubscriptionsController::create', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->delete('push-subscriptions', 'PushSubscriptionsController::delete', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
});

// Compatibility shims: same v1 implementation, with machine-readable sunset metadata.
$legacyApiOptions = [
    'namespace' => 'App\Controllers\Api\V1',
    'filter' => ['apiFormat', 'auth', 'deprecation:/api/v1/messages'],
];
$routes->get('chat/jsonBackend', 'MessagesController::list', $legacyApiOptions);
$routes->get('chat/vueApi', 'MessagesController::list', $legacyApiOptions);
$routes->get('chat/svelteApi', 'MessagesController::list', $legacyApiOptions);

// CSP report route
$routes->post('csp-report', 'CspReport::index');
$routes->get('admin/csp-reports', 'CspReport::admin', ['filter' => 'auth']);
$routes->get('admin/audit-log', 'AuditLog::index', ['filter' => 'auth']);
