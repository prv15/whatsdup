-- =============================================================================
-- WhatsdUP - Super Admin Quick Setup / Reset for phpMyAdmin
-- Paste and run this in phpMyAdmin SQL tab to create or reset the Super Admin
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Ensure Super Admin platform role exists
INSERT INTO `roles` (`id`, `name`, `scope`, `is_system`, `created_at`) VALUES
('d02bf0f3-89ca-4722-8319-480f766995ee', 'Super Admin', 'platform', 1, UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `scope` = 'platform';

-- 2. Ensure all platform permissions exist
INSERT IGNORE INTO `permissions` (`id`, `name`, `created_at`) VALUES
(UUID(), 'admin.dashboard.view', UTC_TIMESTAMP()),
(UUID(), 'businesses.view', UTC_TIMESTAMP()),
(UUID(), 'businesses.create', UTC_TIMESTAMP()),
(UUID(), 'businesses.update', UTC_TIMESTAMP()),
(UUID(), 'users.view', UTC_TIMESTAMP()),
(UUID(), 'users.manage', UTC_TIMESTAMP()),
(UUID(), 'plans.view', UTC_TIMESTAMP()),
(UUID(), 'plans.manage', UTC_TIMESTAMP()),
(UUID(), 'meta_connections.view_all', UTC_TIMESTAMP()),
(UUID(), 'queue.view', UTC_TIMESTAMP()),
(UUID(), 'audit_logs.view', UTC_TIMESTAMP()),
(UUID(), 'webhooks.view', UTC_TIMESTAMP()),
(UUID(), 'system_health.view', UTC_TIMESTAMP()),
(UUID(), 'system_settings.manage', UTC_TIMESTAMP());

-- 3. Link all platform permissions to Super Admin role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 'd02bf0f3-89ca-4722-8319-480f766995ee', p.`id`
FROM `permissions` p
WHERE p.`name` IN (
    'admin.dashboard.view',
    'businesses.view',
    'businesses.create',
    'businesses.update',
    'users.view',
    'users.manage',
    'plans.view',
    'plans.manage',
    'meta_connections.view_all',
    'queue.view',
    'audit_logs.view',
    'webhooks.view',
    'system_health.view',
    'system_settings.manage'
);

-- 4. Create or reset the Super Admin account (Password: Password1234!)
INSERT INTO `users` (
    `id`, `name`, `email`, `password_hash`, `email_verified_at`, `status`,
    `failed_login_attempts`, `locked_until`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`
) VALUES (
    'e01296d3-279a-4a94-9a84-bba49e359245',
    'Super Admin',
    'admin@whatstheup.com',
    '$2y$12$HaZDfMK9VNxphZLo9tJ/kOaqPrUngOiKD4zRivr6dF2YHqpQcEKzO',
    UTC_TIMESTAMP(),
    'active',
    0,
    NULL,
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP(),
    UTC_TIMESTAMP(),
    NULL
)
ON DUPLICATE KEY UPDATE
    `password_hash` = '$2y$12$HaZDfMK9VNxphZLo9tJ/kOaqPrUngOiKD4zRivr6dF2YHqpQcEKzO',
    `status` = 'active',
    `locked_until` = NULL,
    `failed_login_attempts` = 0,
    `deleted_at` = NULL;

-- 5. Link Super Admin user to the Super Admin platform role
INSERT INTO `user_platform_roles` (`user_id`, `role_id`, `assigned_by`, `created_at`) VALUES
('e01296d3-279a-4a94-9a84-bba49e359245', 'd02bf0f3-89ca-4722-8319-480f766995ee', 'e01296d3-279a-4a94-9a84-bba49e359245', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `role_id` = 'd02bf0f3-89ca-4722-8319-480f766995ee';

SET FOREIGN_KEY_CHECKS = 1;

-- Finished! Login at https://app.whatsdup.in/login with:
-- Email: admin@whatstheup.com
-- Password: Password1234!