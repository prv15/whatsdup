<?php

declare(strict_types=1);

use WhatstheUp\Support\Request;

$router->add('GET', '/api/v1/health', static fn () => ['status' => 'ok', 'service' => 'whatstheup-api', 'time' => gmdate(DATE_ATOM)]);
$router->add('POST', '/api/v1/auth/login', [$controller, 'login']);
$router->add('POST', '/api/v1/auth/refresh', [$controller, 'refresh']);
$router->add('POST', '/api/v1/auth/logout', [$controller, 'logout']);
$router->add('GET', '/api/v1/auth/me', [$controller, 'me'], [$authenticate]);
$router->add('POST', '/api/v1/auth/logout-all', [$controller, 'logoutAll'], [$authenticate]);
$router->add('GET', '/api/v1/dashboard', static fn (Request $request) => ['data' => ['business' => $request->attributes['identity']['business'], 'metrics' => ['messagesToday' => 0, 'contacts' => 0, 'approvedTemplates' => 0, 'scheduledCampaigns' => 0]]], [$authenticate]);
$router->add('GET', '/api/v1/meta/configuration', [$metaController, 'configuration'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('GET', '/api/v1/meta/connection', [$metaController, 'status'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('POST', '/api/v1/meta/connection/complete', [$metaController, 'complete'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('GET', '/api/v1/admin/dashboard', [$adminController, 'dashboard'], [$authenticate, $permission('admin.dashboard.view')]);
$router->add('GET', '/api/v1/admin/businesses', [$adminController, 'businesses'], [$authenticate, $permission('businesses.view')]);
$router->add('POST', '/api/v1/admin/businesses', [$adminController, 'createBusiness'], [$authenticate, $permission('businesses.create')]);
$router->add('PATCH', '/api/v1/admin/businesses/{id}', [$adminController, 'updateBusiness'], [$authenticate, $permission('businesses.update')]);
$router->add('GET', '/api/v1/admin/users', [$adminController, 'users'], [$authenticate, $permission('users.view')]);
