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
