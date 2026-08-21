<?php

declare(strict_types=1);

use WhatstheUp\Support\Request;

$router->add('GET', '/api/v1/health', static fn () => ['status' => 'ok', 'service' => 'whatstheup-api', 'time' => gmdate(DATE_ATOM)]);
$router->add('GET', '/api/v1/meta/webhook', [$metaWebhookController, 'verify']);
$router->add('POST', '/api/v1/meta/webhook', [$metaWebhookController, 'receive']);
$router->add('POST', '/api/v1/auth/login', [$controller, 'login']);
$router->add('POST', '/api/v1/auth/forgot-password', [$controller, 'forgotPassword']);
$router->add('POST', '/api/v1/auth/reset-password', [$controller, 'resetPassword']);
$router->add('POST', '/api/v1/auth/refresh', [$controller, 'refresh']);
$router->add('POST', '/api/v1/auth/logout', [$controller, 'logout']);
$router->add('GET', '/api/v1/auth/me', [$controller, 'me'], [$authenticate]);
$router->add('POST', '/api/v1/auth/logout-all', [$controller, 'logoutAll'], [$authenticate]);
$router->add('GET', '/api/v1/dashboard', [$operationsController, 'dashboard'], [$authenticate, $scope('business'), $permission('dashboard.view')]);
$router->add('GET', '/api/v1/contacts', [$operationsController, 'contacts'], [$authenticate, $scope('business'), $permission('contacts.view')]);
$router->add('POST', '/api/v1/contacts/import', [$operationsController, 'importContacts'], [$authenticate, $scope('business'), $permission('contacts.import')]);
$router->add('PATCH', '/api/v1/contacts/{id}', [$operationsController, 'updateContact'], [$authenticate, $scope('business'), $permission('contacts.update')]);
$router->add('DELETE', '/api/v1/contacts/{id}', [$operationsController, 'deleteContact'], [$authenticate, $scope('business'), $permission('contacts.update')]);
$router->add('GET', '/api/v1/contact-groups', [$operationsController, 'contactGroups'], [$authenticate, $scope('business'), $permission('contacts.view')]);
$router->add('POST', '/api/v1/contact-groups', [$operationsController, 'createContactGroup'], [$authenticate, $scope('business'), $permission('contacts.update')]);
$router->add('PATCH', '/api/v1/contact-groups/{id}', [$operationsController, 'updateContactGroup'], [$authenticate, $scope('business'), $permission('contacts.update')]);
$router->add('DELETE', '/api/v1/contact-groups/{id}', [$operationsController, 'deleteContactGroup'], [$authenticate, $scope('business'), $permission('contacts.update')]);
$router->add('GET', '/api/v1/templates', [$operationsController, 'templates'], [$authenticate, $scope('business'), $permission('templates.view')]);
$router->add('POST', '/api/v1/templates', [$operationsController, 'createTemplate'], [$authenticate, $scope('business'), $permission('templates.create')]);
$router->add('PUT', '/api/v1/templates/{id}', [$operationsController, 'updateTemplate'], [$authenticate, $scope('business'), $permission('templates.create')]);
$router->add('DELETE', '/api/v1/templates/{id}', [$operationsController, 'deleteTemplate'], [$authenticate, $scope('business'), $permission('templates.create')]);
$router->add('GET', '/api/v1/campaigns', [$operationsController, 'campaigns'], [$authenticate, $scope('business'), $permission('campaigns.view')]);
$router->add('POST', '/api/v1/campaigns', [$operationsController, 'createCampaign'], [$authenticate, $scope('business'), $permission('campaigns.create')]);
$router->add('PATCH', '/api/v1/campaigns/{id}', [$operationsController, 'updateCampaign'], [$authenticate, $scope('business'), $permission('campaigns.create')]);
$router->add('DELETE', '/api/v1/campaigns/{id}', [$operationsController, 'deleteCampaign'], [$authenticate, $scope('business'), $permission('campaigns.create')]);
$router->add('POST', '/api/v1/campaigns/{id}/launch', [$operationsController, 'launchCampaign'], [$authenticate, $scope('business'), $permission('campaigns.send')]);
$router->add('GET', '/api/v1/meta/configuration', [$metaController, 'configuration'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('GET', '/api/v1/meta/connection', [$metaController, 'status'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('POST', '/api/v1/meta/connection/complete', [$metaController, 'complete'], [$authenticate, $scope('business'), $permission('settings.manage')]);
$router->add('POST', '/api/v1/meta/templates/sync', [$metaController, 'syncTemplates'], [$authenticate, $scope('business'), $permission('templates.sync')]);
$router->add('GET', '/api/v1/admin/dashboard', [$adminController, 'dashboard'], [$authenticate, $permission('admin.dashboard.view')]);
$router->add('GET', '/api/v1/admin/businesses', [$adminController, 'businesses'], [$authenticate, $permission('businesses.view')]);
$router->add('POST', '/api/v1/admin/businesses', [$adminController, 'createBusiness'], [$authenticate, $permission('businesses.create')]);
$router->add('PATCH', '/api/v1/admin/businesses/{id}', [$adminController, 'updateBusiness'], [$authenticate, $permission('businesses.update')]);
$router->add('GET', '/api/v1/admin/users', [$adminController, 'users'], [$authenticate, $permission('users.view')]);
$router->add('GET', '/api/v1/admin/plans', [$adminController, 'plans'], [$authenticate, $permission('plans.view')]);
$router->add('POST', '/api/v1/admin/plans', [$adminController, 'createPlan'], [$authenticate, $permission('plans.manage')]);
$router->add('PUT', '/api/v1/admin/plans/{id}', [$adminController, 'updatePlan'], [$authenticate, $permission('plans.manage')]);
