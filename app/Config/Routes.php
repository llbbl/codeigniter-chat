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

// Profile routes
$routes->get('profile', 'Profile::index', ['filter' => 'auth']);
$routes->post('profile', 'Profile::update', ['filter' => ['auth', 'rate:write']]);
$routes->post('profile/avatar', 'Profile::uploadAvatar', ['filter' => ['auth', 'rate:write']]);
$routes->get('profile/avatar/(:num)', 'Profile::avatar/$1', ['filter' => 'auth']);

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
$routes->get('api/docs', 'ApiDocs::index');

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function (RouteCollection $routes): void {
    $routes->get('messages', 'MessagesController::list', ['filter' => ['apiFormat', 'auth']]);
    $routes->get('messages/search', 'MessagesController::search', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('messages', 'MessagesController::create', ['filter' => ['apiFormat', 'auth', 'rate:write', 'validate:message']]);
    $routes->get('channels', 'ChannelsController::list', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('channels', 'ChannelsController::create', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->patch('channels/(:num)', 'ChannelsController::update/$1', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->post('channels/(:num)/join', 'ChannelsController::join/$1', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->post('channels/(:num)/leave', 'ChannelsController::leave/$1', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->get('channels/(:num)/messages', 'ChannelsController::messages/$1', ['filter' => ['apiFormat', 'auth']]);
    $routes->get('channels/(:num)/messages/search', 'ChannelsController::searchMessages/$1', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('channels/(:num)/messages', 'ChannelsController::createMessage/$1', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->get('channels/(:num)/export', 'ChannelsController::exportChannel/$1', ['filter' => ['auth', 'rate:export']]);
    $routes->get('users/me/export', 'ChannelsController::exportMine', ['filter' => ['auth', 'rate:export']]);
    $routes->post('dms', 'ChannelsController::createDm', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->get('messages/(:num)/reactions', 'ReactionsController::list/$1', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('messages/(:num)/reactions', 'ReactionsController::create/$1', ['filter' => ['apiFormat', 'auth', 'rate:react']]);
    $routes->delete('messages/(:num)/reactions/(:segment)', 'ReactionsController::delete/$1/$2', ['filter' => ['apiFormat', 'auth', 'rate:react']]);
    $routes->get('messages/xml', 'MessagesController::listXml', ['filter' => 'auth']);
    $routes->post('push-subscriptions', 'PushSubscriptionsController::create', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->delete('push-subscriptions', 'PushSubscriptionsController::delete', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->get('profile', '\\App\\Controllers\\Profile::show', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('profile', '\\App\\Controllers\\Profile::update', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->post('profile/avatar', '\\App\\Controllers\\Profile::uploadAvatar', ['filter' => ['apiFormat', 'auth', 'rate:write']]);
    $routes->get('profiles', '\\App\\Controllers\\Profile::profiles', ['filter' => ['apiFormat', 'auth']]);
    $routes->get('webhooks', 'WebhooksController::list', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('webhooks', 'WebhooksController::create', ['filter' => ['apiFormat', 'auth', 'rate:webhook']]);
    $routes->patch('webhooks/(:num)', 'WebhooksController::update/$1', ['filter' => ['apiFormat', 'auth', 'rate:webhook']]);
    $routes->delete('webhooks/(:num)', 'WebhooksController::delete/$1', ['filter' => ['apiFormat', 'auth', 'rate:webhook']]);
    $routes->get('webhooks/(:num)/deliveries', 'WebhooksController::deliveries/$1', ['filter' => ['apiFormat', 'auth']]);
    $routes->post('webhook-deliveries/(:num)/redeliver', 'WebhooksController::redeliver/$1', ['filter' => ['apiFormat', 'auth', 'rate:webhook']]);
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
