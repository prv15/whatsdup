<?php

declare(strict_types=1);

use WhatstheUp\Support\Uuid;

$permissionNames = [
    'dashboard.view', 'contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete', 'contacts.import', 'contacts.export',
    'templates.view', 'templates.create', 'templates.submit', 'templates.sync', 'campaigns.view', 'campaigns.create', 'campaigns.send',
    'campaigns.schedule', 'campaigns.pause', 'campaigns.cancel', 'reports.view', 'settings.manage', 'team.manage', 'audit_logs.view',
];
$roles = [
    'Business Owner' => $permissionNames,
    'Business Admin' => $permissionNames,
    'Campaign Manager' => array_values(array_filter($permissionNames, static fn (string $p) => !in_array($p, ['settings.manage', 'team.manage', 'audit_logs.view'], true))),
    'Viewer' => ['dashboard.view', 'contacts.view', 'templates.view', 'campaigns.view', 'reports.view'],
    'Super Admin' => $permissionNames,
];

$db->beginTransaction();
try {
    $permissionStatement = $db->prepare('INSERT INTO permissions (id, name, created_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name = VALUES(name)');
    foreach ($permissionNames as $permission) {
        $permissionStatement->execute([Uuid::v4(), $permission]);
    }
    foreach ($roles as $name => $grants) {
        $scope = $name === 'Super Admin' ? 'platform' : 'business';
        $db->prepare('INSERT INTO roles (id, name, scope, is_system, created_at) VALUES (?, ?, ?, TRUE, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name = VALUES(name)')->execute([Uuid::v4(), $name, $scope]);
        $roleStatement = $db->prepare('SELECT id FROM roles WHERE name = ? AND scope = ?');
        $roleStatement->execute([$name, $scope]);
        $roleId = $roleStatement->fetchColumn();
        $grant = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE name = ?');
        foreach ($grants as $permission) {
            $grant->execute([$roleId, $permission]);
        }
    }
    $db->commit();
} catch (Throwable $exception) {
    $db->rollBack();
    throw $exception;
}
