-- =============================================================================
-- WhatsdUP WhatsApp Business SaaS - Complete phpMyAdmin Database Setup Script
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- Encoding: utf8mb4 / utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Table structure for `audit_logs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` char(36) NOT NULL,
  `business_id` char(36) DEFAULT NULL,
  `user_id` char(36) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `subject_type` varchar(100) NOT NULL,
  `subject_id` char(36) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_audit_tenant_time` (`business_id`,`created_at`),
  KEY `idx_audit_subject` (`subject_type`,`subject_id`),
  CONSTRAINT `fk_audit_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `business_users`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `business_users` (
  `business_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `status` enum('invited','active','suspended') NOT NULL DEFAULT 'invited',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`business_id`,`user_id`),
  KEY `idx_business_users_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_business_users_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_business_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `businesses`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `businesses` (
  `id` char(36) NOT NULL,
  `reseller_id` char(36) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `legal_name` varchar(190) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `language` varchar(12) NOT NULL DEFAULT 'en',
  `default_country_code` varchar(8) DEFAULT NULL,
  `status` enum('pending','active','suspended','archived') NOT NULL DEFAULT 'pending',
  `access_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_businesses_owner_status` (`reseller_id`,`status`),
  KEY `idx_businesses_status` (`status`),
  CONSTRAINT `fk_businesses_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `campaign_contacts`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_contacts` (
  `campaign_id` char(36) NOT NULL,
  `contact_id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `phone_e164` varchar(32) NOT NULL,
  `status` enum('queued','accepted','sent','delivered','read','failed','skipped') NOT NULL DEFAULT 'queued',
  `meta_message_id` varchar(128) DEFAULT NULL,
  `failure_code` varchar(120) DEFAULT NULL,
  `failure_message` varchar(500) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`campaign_id`,`contact_id`),
  KEY `fk_campaign_contacts_contact` (`contact_id`),
  KEY `idx_campaign_contacts_status` (`campaign_id`,`status`),
  KEY `idx_campaign_contacts_tenant` (`business_id`,`status`),
  KEY `idx_campaign_contacts_meta_message` (`meta_message_id`),
  CONSTRAINT `fk_campaign_contacts_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_campaign_contacts_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_contacts_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `campaign_groups`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_groups` (
  `campaign_id` char(36) NOT NULL,
  `group_id` char(36) NOT NULL,
  PRIMARY KEY (`campaign_id`,`group_id`),
  KEY `idx_campaign_groups_group` (`group_id`),
  CONSTRAINT `fk_campaign_groups_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_groups_group` FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `campaigns`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `template_id` char(36) NOT NULL,
  `variable_mappings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variable_mappings`)),
  `name` varchar(190) NOT NULL,
  `audience_type` enum('all_opted_in','selected','groups') NOT NULL DEFAULT 'all_opted_in',
  `status` enum('draft','scheduled','queued','processing','dispatched','completed','paused','cancelled','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `launched_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `recipient_count` int(10) unsigned NOT NULL DEFAULT 0,
  `delivered_count` int(10) unsigned NOT NULL DEFAULT 0,
  `read_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` char(36) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_campaigns_template` (`template_id`),
  KEY `fk_campaigns_creator` (`created_by`),
  KEY `idx_campaigns_tenant_status` (`business_id`,`status`,`scheduled_at`),
  KEY `idx_campaigns_tenant_created` (`business_id`,`created_at`),
  CONSTRAINT `fk_campaigns_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_campaigns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_campaigns_template` FOREIGN KEY (`template_id`) REFERENCES `message_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `contact_group_members`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_group_members` (
  `group_id` char(36) NOT NULL,
  `contact_id` char(36) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`group_id`,`contact_id`),
  KEY `idx_contact_group_members_contact` (`contact_id`),
  CONSTRAINT `fk_contact_group_members_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `contact_groups`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_groups` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(300) DEFAULT NULL,
  `created_by` char(36) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_groups_name` (`business_id`,`name`),
  KEY `fk_contact_groups_creator` (`created_by`),
  KEY `idx_contact_groups_tenant` (`business_id`,`deleted_at`,`created_at`),
  CONSTRAINT `fk_contact_groups_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_contact_groups_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `contact_imports`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_imports` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `created_by` char(36) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` enum('completed','completed_with_errors','failed') NOT NULL,
  `total_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `imported_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`errors`)),
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_contact_imports_user` (`created_by`),
  KEY `idx_contact_imports_tenant_created` (`business_id`,`created_at`),
  CONSTRAINT `fk_contact_imports_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_contact_imports_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `contacts`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `phone_e164` varchar(32) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`tags`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`custom_fields`)),
  `consent_status` enum('opted_in','opted_out','unknown') NOT NULL DEFAULT 'unknown',
  `consent_at` datetime DEFAULT NULL,
  `source` varchar(80) NOT NULL DEFAULT 'manual',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contacts_phone` (`business_id`,`phone_e164`),
  KEY `idx_contacts_tenant_consent` (`business_id`,`consent_status`,`deleted_at`),
  KEY `idx_contacts_tenant_created` (`business_id`,`created_at`),
  CONSTRAINT `fk_contacts_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `conversation_messages`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversation_messages` (
  `id` char(36) NOT NULL,
  `conversation_id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `sender_user_id` char(36) DEFAULT NULL,
  `message_type` enum('text','image','document','template','interactive') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `meta_message_id` varchar(128) DEFAULT NULL,
  `status` enum('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
  `error_code` varchar(120) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_conv_messages_sender` (`sender_user_id`),
  KEY `idx_conv_messages_thread` (`conversation_id`,`created_at`),
  KEY `idx_conv_messages_meta` (`meta_message_id`),
  KEY `idx_conv_messages_tenant` (`business_id`,`created_at`),
  CONSTRAINT `fk_conv_messages_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_conv_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `conversations`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `contact_id` char(36) NOT NULL,
  `meta_phone_number_id` varchar(64) DEFAULT NULL,
  `status` enum('open','closed','snoozed') NOT NULL DEFAULT 'open',
  `last_message_at` datetime NOT NULL,
  `window_expires_at` datetime DEFAULT NULL,
  `unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `assigned_user_id` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversations_business_contact` (`business_id`,`contact_id`),
  KEY `fk_conversations_contact` (`contact_id`),
  KEY `fk_conversations_assignee` (`assigned_user_id`),
  KEY `idx_conversations_tenant_status` (`business_id`,`status`,`last_message_at`),
  KEY `idx_conversations_tenant_assigned` (`business_id`,`assigned_user_id`),
  CONSTRAINT `fk_conversations_assignee` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversations_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_conversations_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `email_verifications`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `fk_email_verifications_user` (`user_id`),
  KEY `idx_email_verifications_expiry` (`expires_at`),
  CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `encrypted_tokens`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `encrypted_tokens` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `provider` varchar(40) NOT NULL,
  `ciphertext` longtext NOT NULL,
  `nonce` varchar(255) NOT NULL,
  `key_version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_encrypted_tokens_tenant_provider` (`business_id`,`provider`),
  CONSTRAINT `fk_encrypted_tokens_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `failed_jobs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_job_id` bigint(20) unsigned NOT NULL,
  `business_id` char(36) DEFAULT NULL,
  `queue` varchar(80) NOT NULL,
  `job_type` varchar(190) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `idempotency_key` varchar(190) NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `error_code` varchar(120) DEFAULT NULL,
  `error_message` text NOT NULL,
  `failed_at` datetime NOT NULL,
  `retried_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_failed_queue_time` (`queue`,`failed_at`),
  KEY `idx_failed_tenant_time` (`business_id`,`failed_at`),
  CONSTRAINT `fk_failed_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `message_templates`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `meta_template_id` varchar(128) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `language` varchar(20) NOT NULL DEFAULT 'en_US',
  `category` enum('marketing','utility','authentication') NOT NULL DEFAULT 'marketing',
  `header_type` enum('none','image') NOT NULL DEFAULT 'none',
  `header_media_url` varchar(500) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `status` enum('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `created_by` char(36) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_templates_name_language` (`business_id`,`name`,`language`),
  KEY `fk_templates_creator` (`created_by`),
  KEY `idx_templates_tenant_status` (`business_id`,`status`,`deleted_at`),
  CONSTRAINT `fk_templates_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_templates_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `meta_api_logs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `meta_api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` char(36) DEFAULT NULL,
  `correlation_id` char(36) NOT NULL,
  `operation` varchar(120) NOT NULL,
  `graph_version` varchar(16) NOT NULL,
  `http_status` smallint(5) unsigned DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `error_code` varchar(120) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_meta_logs_tenant_time` (`business_id`,`created_at`),
  KEY `idx_meta_logs_operation_status` (`operation`,`success`,`created_at`),
  CONSTRAINT `fk_meta_logs_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `meta_connections`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `meta_connections` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `token_id` char(36) DEFAULT NULL,
  `meta_business_id` varchar(64) DEFAULT NULL,
  `app_id` varchar(64) NOT NULL,
  `status` enum('not_connected','connecting','connected','action_required','verification_required','token_invalid','restricted','disconnected','webhook_error') NOT NULL DEFAULT 'not_connected',
  `connected_by` char(36) DEFAULT NULL,
  `connected_at` datetime DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `last_tested_at` datetime DEFAULT NULL,
  `last_error_code` varchar(120) DEFAULT NULL,
  `last_error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meta_connection_business` (`business_id`),
  KEY `fk_meta_connections_token` (`token_id`),
  KEY `fk_meta_connections_user` (`connected_by`),
  KEY `idx_meta_connections_status` (`status`,`last_synced_at`),
  CONSTRAINT `fk_meta_connections_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_meta_connections_token` FOREIGN KEY (`token_id`) REFERENCES `encrypted_tokens` (`id`),
  CONSTRAINT `fk_meta_connections_user` FOREIGN KEY (`connected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `migrations`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `password_resets`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `fk_password_resets_user` (`user_id`),
  KEY `idx_password_resets_expiry` (`expires_at`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `permissions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `plan_features`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_features` (
  `plan_id` char(36) NOT NULL,
  `feature_key` varchar(120) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  PRIMARY KEY (`plan_id`,`feature_key`),
  CONSTRAINT `fk_plan_features_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `plans`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id` char(36) NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(80) NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `price_minor` int(10) unsigned DEFAULT NULL,
  `annual_price_minor` int(10) unsigned DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `billing_interval` enum('month','year','custom') NOT NULL DEFAULT 'month',
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `limits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`limits`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `queue_jobs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `queue_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` char(36) DEFAULT NULL,
  `queue` varchar(80) NOT NULL,
  `job_type` varchar(190) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `idempotency_key` varchar(190) NOT NULL,
  `trace_id` char(36) NOT NULL,
  `status` enum('ready','reserved','completed','failed','cancelled') NOT NULL DEFAULT 'ready',
  `priority` smallint(6) NOT NULL DEFAULT 100,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_attempts` smallint(5) unsigned NOT NULL DEFAULT 5,
  `available_at` datetime NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `lock_expires_at` datetime DEFAULT NULL,
  `lock_token` char(36) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error_code` varchar(120) DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_queue_idempotency` (`queue`,`idempotency_key`),
  KEY `idx_queue_claim` (`queue`,`status`,`available_at`,`priority`,`id`),
  KEY `idx_queue_stale` (`status`,`lock_expires_at`),
  KEY `idx_queue_tenant` (`business_id`,`queue`,`status`),
  CONSTRAINT `fk_queue_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `rate_limits`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `rate_key` varchar(190) NOT NULL,
  `tokens` double NOT NULL,
  `last_refill` decimal(16,4) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`rate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `reseller_branding`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reseller_branding` (
  `reseller_id` char(36) NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings`)),
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`reseller_id`),
  CONSTRAINT `fk_reseller_branding_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `reseller_domains`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reseller_domains` (
  `id` char(36) NOT NULL,
  `reseller_id` char(36) NOT NULL,
  `hostname` varchar(253) NOT NULL,
  `status` enum('pending','verified','failed') NOT NULL DEFAULT 'pending',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  KEY `idx_reseller_domains_owner` (`reseller_id`,`status`),
  CONSTRAINT `fk_reseller_domains_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `reseller_users`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reseller_users` (
  `reseller_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `role_key` varchar(64) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`reseller_id`,`user_id`),
  KEY `fk_reseller_users_user` (`user_id`),
  CONSTRAINT `fk_reseller_users_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`),
  CONSTRAINT `fk_reseller_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `resellers`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `resellers` (
  `id` char(36) NOT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `status` enum('active','suspended','archived') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_resellers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `role_permissions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` char(36) NOT NULL,
  `permission_id` char(36) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `roles`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` char(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `scope` enum('platform','reseller','business') NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name_scope` (`name`,`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `scheduled_jobs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scheduled_jobs` (
  `id` char(36) NOT NULL,
  `business_id` char(36) DEFAULT NULL,
  `job_key` varchar(190) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `due_at` datetime NOT NULL,
  `status` enum('scheduled','dispatching','dispatched','cancelled','failed') NOT NULL DEFAULT 'scheduled',
  `lock_token` char(36) DEFAULT NULL,
  `lock_expires_at` datetime DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scheduled_job` (`business_id`,`job_key`),
  KEY `idx_scheduled_due` (`status`,`due_at`),
  KEY `idx_scheduled_stale` (`status`,`lock_expires_at`),
  CONSTRAINT `fk_scheduled_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `security_logs`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` char(36) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `event_type` varchar(120) NOT NULL,
  `identifier` varchar(254) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_security_logs_user` (`user_id`),
  KEY `idx_security_event_time` (`event_type`,`created_at`),
  KEY `idx_security_identifier_time` (`identifier`,`created_at`),
  KEY `idx_security_ip_time` (`ip_address`,`created_at`),
  CONSTRAINT `fk_security_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `subscription_usage`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscription_usage` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `subscription_id` char(36) NOT NULL,
  `metric_key` varchar(120) NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `quantity` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage_period` (`business_id`,`subscription_id`,`metric_key`,`period_start`),
  KEY `fk_usage_subscription` (`subscription_id`),
  KEY `idx_usage_tenant_period` (`business_id`,`period_start`,`period_end`),
  CONSTRAINT `fk_usage_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_usage_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `subscriptions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `plan_id` char(36) NOT NULL,
  `provider` varchar(40) NOT NULL DEFAULT 'manual',
  `status` enum('trialing','active','past_due','suspended','cancelled','expired') NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_subscriptions_plan` (`plan_id`),
  KEY `idx_subscriptions_business_status` (`business_id`,`status`,`ends_at`),
  CONSTRAINT `fk_subscriptions_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `system_settings`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(190) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`value`)),
  `is_secret` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` char(36) DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_key`),
  KEY `fk_system_settings_user` (`updated_by`),
  CONSTRAINT `fk_system_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `user_platform_roles`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_platform_roles` (
  `user_id` char(36) NOT NULL,
  `role_id` char(36) NOT NULL,
  `assigned_by` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_platform_roles_assigner` (`assigned_by`),
  KEY `idx_platform_roles_role` (`role_id`,`user_id`),
  CONSTRAINT `fk_platform_roles_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_platform_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_platform_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `user_roles`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
  `business_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `role_id` char(36) NOT NULL,
  `assigned_by` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`business_id`,`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  KEY `fk_user_roles_assigner` (`assigned_by`),
  KEY `idx_user_roles_user_business` (`user_id`,`business_id`),
  CONSTRAINT `fk_user_roles_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_user_roles_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `user_sessions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `token_family` char(36) NOT NULL,
  `refresh_token_hash` char(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refresh_token_hash` (`refresh_token_hash`),
  KEY `idx_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  KEY `idx_sessions_family` (`token_family`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` char(36) NOT NULL,
  `name` varchar(190) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `status` enum('invited','active','suspended') NOT NULL DEFAULT 'invited',
  `failed_login_attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_lock` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `waba_accounts`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `waba_accounts` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `meta_connection_id` char(36) NOT NULL,
  `meta_waba_id` varchar(64) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `currency` varchar(12) DEFAULT NULL,
  `timezone_id` varchar(64) DEFAULT NULL,
  `review_status` varchar(64) DEFAULT NULL,
  `status` varchar(64) DEFAULT NULL,
  `last_synced_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_waba_meta_id` (`meta_waba_id`),
  KEY `fk_waba_connection` (`meta_connection_id`),
  KEY `idx_waba_tenant_status` (`business_id`,`status`),
  CONSTRAINT `fk_waba_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_waba_connection` FOREIGN KEY (`meta_connection_id`) REFERENCES `meta_connections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `webhook_events`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_id` char(36) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `idempotency_key` varchar(190) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('received','processed','failed') NOT NULL DEFAULT 'received',
  `error_message` varchar(500) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `idx_webhook_events_status` (`status`,`created_at`),
  KEY `idx_webhook_events_tenant` (`business_id`,`created_at`),
  CONSTRAINT `fk_webhook_events_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `webhook_subscriptions`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_subscriptions` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `waba_account_id` char(36) NOT NULL,
  `status` enum('pending','active','failed','disconnected') NOT NULL DEFAULT 'pending',
  `subscribed_at` datetime DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_webhook_waba` (`waba_account_id`),
  KEY `idx_webhook_sub_tenant_status` (`business_id`,`status`),
  CONSTRAINT `fk_webhook_sub_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_webhook_sub_waba` FOREIGN KEY (`waba_account_id`) REFERENCES `waba_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `whatsapp_phone_numbers`
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `whatsapp_phone_numbers` (
  `id` char(36) NOT NULL,
  `business_id` char(36) NOT NULL,
  `waba_account_id` char(36) NOT NULL,
  `meta_phone_number_id` varchar(64) NOT NULL,
  `display_phone_number` varchar(40) DEFAULT NULL,
  `verified_name` varchar(190) DEFAULT NULL,
  `quality_rating` varchar(40) DEFAULT NULL,
  `name_status` varchar(64) DEFAULT NULL,
  `registration_status` varchar(64) DEFAULT NULL,
  `connection_status` varchar(64) NOT NULL DEFAULT 'connected',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `last_synced_at` datetime DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `last_webhook_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_phone_meta_id` (`meta_phone_number_id`),
  KEY `fk_phone_waba` (`waba_account_id`),
  KEY `idx_phone_tenant_status` (`business_id`,`connection_status`,`is_default`),
  CONSTRAINT `fk_phone_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  CONSTRAINT `fk_phone_waba` FOREIGN KEY (`waba_account_id`) REFERENCES `waba_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Core foundational data for `migrations`
-- -----------------------------------------------------------------------------
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES
(1, '001_foundation.sql', '2026-09-19 11:04:49'),
(2, '002_platform_admin.sql', '2026-09-19 11:04:49'),
(3, '003_meta_connections.sql', '2026-09-19 11:04:49'),
(4, '004_plan_catalog.sql', '2026-09-19 11:04:49'),
(5, '005_plan_annual_pricing.sql', '2026-09-19 11:04:49'),
(6, '006_contacts_campaigns.sql', '2026-09-19 11:04:49'),
(7, '007_contact_groups.sql', '2026-09-19 11:04:49'),
(8, '007_template_media.sql', '2026-09-19 11:04:49'),
(9, '008_campaign_message_lookup.sql', '2026-09-19 11:04:49'),
(10, '009_pipeline_hardening.sql', '2026-09-19 11:04:49'),
(11, '010_queue_scaling.sql', '2026-09-19 11:04:49'),
(12, '011_conversations_inbox.sql', '2026-10-01 13:21:02')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `permissions`
-- -----------------------------------------------------------------------------
INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
('06c197df-35c0-4380-ba5e-18508e6afd44', 'contacts.create', NULL, '2026-09-19 05:36:03'),
('13924316-dc36-41c8-a1ef-a309e45e7208', 'dashboard.view', NULL, '2026-09-19 05:36:03'),
('267f58c5-3569-414d-8476-a92980a30f5e', 'team.manage', NULL, '2026-09-19 05:36:03'),
('2bb3aaf2-5d98-45d5-bb3b-7b36190cd10d', 'admin.dashboard.view', NULL, '2026-09-19 05:36:03'),
('327131f5-0d51-4444-b90d-9cb48e4dc870', 'contacts.import', NULL, '2026-09-19 05:36:03'),
('40ad8808-3634-4e11-92db-8ce07d3f86c7', 'campaigns.cancel', NULL, '2026-09-19 05:36:03'),
('43c08569-9ce8-40bf-baf2-8f17620e8c8d', 'users.manage', NULL, '2026-09-19 05:36:03'),
('4841b6ab-292b-4c9a-b017-10e5941177d9', 'campaigns.send', NULL, '2026-09-19 05:36:03'),
('4882117b-4887-4e60-b829-6942848b3729', 'audit_logs.view', NULL, '2026-09-19 05:36:03'),
('4948d816-401e-4149-962c-3f1436873ebc', 'businesses.view', NULL, '2026-09-19 05:36:03'),
('4b2c0330-3665-4276-88eb-a59687472fa9', 'templates.sync', NULL, '2026-09-19 05:36:03'),
('54fa9304-4936-43bd-b17d-eaad50c7d4c4', 'system_health.view', NULL, '2026-09-19 05:36:03'),
('6094ec50-8104-4913-abf4-4b37a43201cc', 'templates.create', NULL, '2026-09-19 05:36:03'),
('67cfc641-68f0-43e2-ab4f-bd9610e09416', 'contacts.delete', NULL, '2026-09-19 05:36:03'),
('8c735d6a-6e8f-4642-a04e-4180ac0efa2a', 'system_settings.manage', NULL, '2026-09-19 05:36:03'),
('8e7dc0ff-df68-43ea-997d-66b71ef2aec1', 'businesses.update', NULL, '2026-09-19 05:36:03'),
('95d4d703-bda1-4fc9-b6d1-2d3623b45bdb', 'campaigns.pause', NULL, '2026-09-19 05:36:03'),
('9ef75917-4840-4b19-b0b5-c9b119113be5', 'plans.view', NULL, '2026-09-19 05:36:03'),
('9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76', 'contacts.view', NULL, '2026-09-19 05:36:03'),
('aa0147b4-d73f-45cf-951d-b315977d70d4', 'campaigns.view', NULL, '2026-09-19 05:36:03'),
('ac880889-2de3-4883-b11f-802c34a95efe', 'campaigns.schedule', NULL, '2026-09-19 05:36:03'),
('ae22458b-fa26-4916-8915-62d3ae90ca70', 'users.view', NULL, '2026-09-19 05:36:03'),
('b4520062-d3a3-42e0-bb90-23fbc5565400', 'contacts.update', NULL, '2026-09-19 05:36:03'),
('b4b8488a-88c6-4ff6-9485-2d6376ddcfd3', 'reports.view', NULL, '2026-09-19 05:36:03'),
('b5e672b6-8256-41f1-a185-3de66b727976', 'settings.manage', NULL, '2026-09-19 05:36:03'),
('b69f06d9-2e06-403c-b330-6a2401c9893b', 'plans.manage', NULL, '2026-09-19 05:36:03'),
('b82846e4-09c5-4667-bd2e-c1553a4e2cb4', 'businesses.create', NULL, '2026-09-19 05:36:03'),
('c8e95929-4c1e-404f-992f-cc080281c9af', 'queue.view', NULL, '2026-09-19 05:36:03'),
('d269aa74-b123-4c5e-ab65-373855978013', 'templates.submit', NULL, '2026-09-19 05:36:03'),
('d58b30c8-20d4-4101-b898-9b841467c525', 'webhooks.view', NULL, '2026-09-19 05:36:03'),
('d963c7da-bd6c-11f1-84bb-5c879c2793b7', 'inbox.view', 'View team inbox conversations and message history', '2026-10-01 07:51:02'),
('d964208d-bd6c-11f1-84bb-5c879c2793b7', 'inbox.send', 'Send outbound two-way messages in team inbox', '2026-10-01 07:51:02'),
('d964277d-bd6c-11f1-84bb-5c879c2793b7', 'inbox.assign', 'Assign conversations to team members', '2026-10-01 07:51:02'),
('de53e31e-a686-43c6-81ec-a158c0f016c4', 'meta_connections.view_all', NULL, '2026-09-19 05:36:03'),
('e48c2edd-344b-42f5-95a8-8e86cf350662', 'campaigns.create', NULL, '2026-09-19 05:36:03'),
('ef8766a7-c27f-4e59-b35f-00004beded26', 'contacts.export', NULL, '2026-09-19 05:36:03'),
('fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a', 'templates.view', NULL, '2026-09-19 05:36:03')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `roles`
-- -----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `scope`, `is_system`, `created_at`) VALUES
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'Business Owner', 'business', 1, '2026-09-19 05:36:03'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'Campaign Manager', 'business', 1, '2026-09-19 05:36:03'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'Business Admin', 'business', 1, '2026-09-19 05:36:03'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'Super Admin', 'platform', 1, '2026-09-19 05:36:03'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', 'Viewer', 'business', 1, '2026-09-19 05:36:03')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `role_permissions`
-- -----------------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '06c197df-35c0-4380-ba5e-18508e6afd44'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '13924316-dc36-41c8-a1ef-a309e45e7208'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '267f58c5-3569-414d-8476-a92980a30f5e'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '327131f5-0d51-4444-b90d-9cb48e4dc870'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '40ad8808-3634-4e11-92db-8ce07d3f86c7'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '4841b6ab-292b-4c9a-b017-10e5941177d9'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '4882117b-4887-4e60-b829-6942848b3729'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '4b2c0330-3665-4276-88eb-a59687472fa9'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '6094ec50-8104-4913-abf4-4b37a43201cc'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '67cfc641-68f0-43e2-ab4f-bd9610e09416'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '95d4d703-bda1-4fc9-b6d1-2d3623b45bdb'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', '9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'aa0147b4-d73f-45cf-951d-b315977d70d4'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'ac880889-2de3-4883-b11f-802c34a95efe'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'b4520062-d3a3-42e0-bb90-23fbc5565400'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'b4b8488a-88c6-4ff6-9485-2d6376ddcfd3'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'b5e672b6-8256-41f1-a185-3de66b727976'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'd269aa74-b123-4c5e-ab65-373855978013'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'd963c7da-bd6c-11f1-84bb-5c879c2793b7'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'd964208d-bd6c-11f1-84bb-5c879c2793b7'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'd964277d-bd6c-11f1-84bb-5c879c2793b7'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'e48c2edd-344b-42f5-95a8-8e86cf350662'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'ef8766a7-c27f-4e59-b35f-00004beded26'),
('4ca6268c-7877-4612-a4a0-4382dc4591a0', 'fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '06c197df-35c0-4380-ba5e-18508e6afd44'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '13924316-dc36-41c8-a1ef-a309e45e7208'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '327131f5-0d51-4444-b90d-9cb48e4dc870'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '40ad8808-3634-4e11-92db-8ce07d3f86c7'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '4841b6ab-292b-4c9a-b017-10e5941177d9'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '4b2c0330-3665-4276-88eb-a59687472fa9'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '6094ec50-8104-4913-abf4-4b37a43201cc'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '67cfc641-68f0-43e2-ab4f-bd9610e09416'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '95d4d703-bda1-4fc9-b6d1-2d3623b45bdb'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', '9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'aa0147b4-d73f-45cf-951d-b315977d70d4'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'ac880889-2de3-4883-b11f-802c34a95efe'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'b4520062-d3a3-42e0-bb90-23fbc5565400'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'b4b8488a-88c6-4ff6-9485-2d6376ddcfd3'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'd269aa74-b123-4c5e-ab65-373855978013'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'd963c7da-bd6c-11f1-84bb-5c879c2793b7'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'd964208d-bd6c-11f1-84bb-5c879c2793b7'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'e48c2edd-344b-42f5-95a8-8e86cf350662'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'ef8766a7-c27f-4e59-b35f-00004beded26'),
('9359bd2c-c387-43a7-93e2-f4b206089bc5', 'fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '06c197df-35c0-4380-ba5e-18508e6afd44'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '13924316-dc36-41c8-a1ef-a309e45e7208'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '267f58c5-3569-414d-8476-a92980a30f5e'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '327131f5-0d51-4444-b90d-9cb48e4dc870'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '40ad8808-3634-4e11-92db-8ce07d3f86c7'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '4841b6ab-292b-4c9a-b017-10e5941177d9')
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
('cb2e01d7-8492-4c19-946f-d521715434e0', '4882117b-4887-4e60-b829-6942848b3729'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '4b2c0330-3665-4276-88eb-a59687472fa9'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '6094ec50-8104-4913-abf4-4b37a43201cc'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '67cfc641-68f0-43e2-ab4f-bd9610e09416'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '95d4d703-bda1-4fc9-b6d1-2d3623b45bdb'),
('cb2e01d7-8492-4c19-946f-d521715434e0', '9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'aa0147b4-d73f-45cf-951d-b315977d70d4'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'ac880889-2de3-4883-b11f-802c34a95efe'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'b4520062-d3a3-42e0-bb90-23fbc5565400'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'b4b8488a-88c6-4ff6-9485-2d6376ddcfd3'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'b5e672b6-8256-41f1-a185-3de66b727976'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'd269aa74-b123-4c5e-ab65-373855978013'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'd963c7da-bd6c-11f1-84bb-5c879c2793b7'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'd964208d-bd6c-11f1-84bb-5c879c2793b7'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'd964277d-bd6c-11f1-84bb-5c879c2793b7'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'e48c2edd-344b-42f5-95a8-8e86cf350662'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'ef8766a7-c27f-4e59-b35f-00004beded26'),
('cb2e01d7-8492-4c19-946f-d521715434e0', 'fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '06c197df-35c0-4380-ba5e-18508e6afd44'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '13924316-dc36-41c8-a1ef-a309e45e7208'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '267f58c5-3569-414d-8476-a92980a30f5e'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '2bb3aaf2-5d98-45d5-bb3b-7b36190cd10d'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '327131f5-0d51-4444-b90d-9cb48e4dc870'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '40ad8808-3634-4e11-92db-8ce07d3f86c7'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '43c08569-9ce8-40bf-baf2-8f17620e8c8d'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '4841b6ab-292b-4c9a-b017-10e5941177d9'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '4882117b-4887-4e60-b829-6942848b3729'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '4948d816-401e-4149-962c-3f1436873ebc'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '4b2c0330-3665-4276-88eb-a59687472fa9'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '54fa9304-4936-43bd-b17d-eaad50c7d4c4'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '6094ec50-8104-4913-abf4-4b37a43201cc'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '67cfc641-68f0-43e2-ab4f-bd9610e09416'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '8c735d6a-6e8f-4642-a04e-4180ac0efa2a'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '8e7dc0ff-df68-43ea-997d-66b71ef2aec1'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '95d4d703-bda1-4fc9-b6d1-2d3623b45bdb'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '9ef75917-4840-4b19-b0b5-c9b119113be5'),
('d02bf0f3-89ca-4722-8319-480f766995ee', '9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'aa0147b4-d73f-45cf-951d-b315977d70d4'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'ac880889-2de3-4883-b11f-802c34a95efe'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'ae22458b-fa26-4916-8915-62d3ae90ca70'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'b4520062-d3a3-42e0-bb90-23fbc5565400'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'b4b8488a-88c6-4ff6-9485-2d6376ddcfd3'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'b5e672b6-8256-41f1-a185-3de66b727976'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'b69f06d9-2e06-403c-b330-6a2401c9893b'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'b82846e4-09c5-4667-bd2e-c1553a4e2cb4'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'c8e95929-4c1e-404f-992f-cc080281c9af'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'd269aa74-b123-4c5e-ab65-373855978013'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'd58b30c8-20d4-4101-b898-9b841467c525'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'd963c7da-bd6c-11f1-84bb-5c879c2793b7'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'd964208d-bd6c-11f1-84bb-5c879c2793b7')
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
('d02bf0f3-89ca-4722-8319-480f766995ee', 'd964277d-bd6c-11f1-84bb-5c879c2793b7'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'de53e31e-a686-43c6-81ec-a158c0f016c4'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'e48c2edd-344b-42f5-95a8-8e86cf350662'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'ef8766a7-c27f-4e59-b35f-00004beded26'),
('d02bf0f3-89ca-4722-8319-480f766995ee', 'fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', '13924316-dc36-41c8-a1ef-a309e45e7208'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', '9f1b49eb-1abc-4bbc-bbb7-3b6ce5973d76'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', 'aa0147b4-d73f-45cf-951d-b315977d70d4'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', 'b4b8488a-88c6-4ff6-9485-2d6376ddcfd3'),
('e78fbfbf-0bfb-4849-88bb-581b8ba2587e', 'fe8aff89-d84d-43cc-8453-b2ef9f0b6b8a')
ON DUPLICATE KEY UPDATE `role_id` = `role_id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `plans`
-- -----------------------------------------------------------------------------
INSERT INTO `plans` (`id`, `name`, `code`, `description`, `price_minor`, `annual_price_minor`, `currency`, `billing_interval`, `status`, `is_public`, `sort_order`, `limits`, `created_at`, `updated_at`) VALUES
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'Launch', 'launch', 'For small businesses beginning with structured WhatsApp campaigns.', 99900, 958800, 'INR', 'month', 'active', 1, 10, '{\"phoneNumbers\": 1, \"teamMembers\": 3, \"contacts\": 5000, \"monthlyRecipients\": 12000}', '2026-09-19 05:34:49', '2026-09-19 05:34:49'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'Growth', 'growth', 'For growing teams running regular campaigns across larger audiences.', 229900, 2206800, 'INR', 'month', 'active', 1, 20, '{\"phoneNumbers\": 2, \"teamMembers\": 10, \"contacts\": 25000, \"monthlyRecipients\": 100000}', '2026-09-19 05:34:49', '2026-09-19 05:34:49'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'Scale', 'scale', 'For established operations needing higher limits and closer support.', NULL, NULL, 'INR', 'custom', 'active', 1, 30, '{\"phoneNumbers\": null, \"teamMembers\": null, \"contacts\": null, \"monthlyRecipients\": null}', '2026-09-19 05:34:49', '2026-09-19 05:34:49')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `plan_features`
-- -----------------------------------------------------------------------------
INSERT INTO `plan_features` (`plan_id`, `feature_key`, `value`) VALUES
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_01', '\"Unlimited campaign creation\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_02', '\"Official Cloud API with 0% markup\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_03', '\"Template sync and approval status\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_04', '\"Scheduled campaign sending\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_05', '\"Consent and opt-out suppression\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_06', '\"Delivery and read reporting\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_07', '\"CSV contact imports\"'),
('d4ca29ec-b3eb-11f1-80cc-5c879c2793b7', 'feature_08', '\"Email support\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_01', '\"Everything in Launch\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_02', '\"Tags, groups and custom fields\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_03', '\"Advanced campaign analytics\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_04', '\"Recipient-level status history\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_05', '\"Failure diagnostics and safe retries\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_06', '\"API and webhook access\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_07', '\"Audit activity history\"'),
('d4ca39cf-b3eb-11f1-80cc-5c879c2793b7', 'feature_08', '\"Priority support\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_01', '\"Everything in Growth\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_02', '\"Advanced roles and permissions\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_03', '\"Operational and audit exports\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_04', '\"Custom API integration support\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_05', '\"Guided Meta onboarding\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_06', '\"Migration and launch assistance\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_07', '\"Priority issue escalation\"'),
('d4ca3bd3-b3eb-11f1-80cc-5c879c2793b7', 'feature_08', '\"Dedicated success contact\"')
ON DUPLICATE KEY UPDATE `plan_id` = `plan_id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `businesses`
-- -----------------------------------------------------------------------------
INSERT INTO `businesses` (`id`, `reseller_id`, `name`, `slug`, `legal_name`, `timezone`, `language`, `default_country_code`, `status`, `access_until`, `created_at`, `updated_at`, `deleted_at`) VALUES
('cdb602cc-f3de-4dca-a626-c03fa675fa85', NULL, 'WhatsdUP Demo', 'whatsdup-demo-cdb602cc', NULL, 'UTC', 'en', NULL, 'active', NULL, '2026-09-19 05:36:39', '2026-09-19 05:36:39', NULL)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `users`
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `email_verified_at`, `status`, `failed_login_attempts`, `locked_until`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
('86c9aaa5-e4ac-415a-ad51-d9ea01213c4c', 'Demo Owner', 'owner@whatstheup.com', '$2y$12$HaZDfMK9VNxphZLo9tJ/kOaqPrUngOiKD4zRivr6dF2YHqpQcEKzO', '2026-09-19 05:36:39', 'active', '0', NULL, '2026-10-01 08:16:38', '2026-09-19 05:36:39', '2026-09-19 05:36:39', NULL),
('e01296d3-279a-4a94-9a84-bba49e359245', 'Super Admin', 'admin@whatstheup.com', '$2y$12$HaZDfMK9VNxphZLo9tJ/kOaqPrUngOiKD4zRivr6dF2YHqpQcEKzO', '2026-09-19 05:36:28', 'active', '0', NULL, '2026-10-01 08:23:27', '2026-09-19 05:36:28', '2026-09-19 05:36:28', NULL)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `status` = 'active', `locked_until` = NULL, `failed_login_attempts` = 0;

-- -----------------------------------------------------------------------------
-- Core foundational data for `business_users`
-- -----------------------------------------------------------------------------
INSERT INTO `business_users` (`business_id`, `user_id`, `status`, `is_primary`, `joined_at`, `created_at`, `updated_at`) VALUES
('cdb602cc-f3de-4dca-a626-c03fa675fa85', '86c9aaa5-e4ac-415a-ad51-d9ea01213c4c', 'active', 1, '2026-09-19 05:36:39', '2026-09-19 05:36:39', '2026-09-19 05:36:39')
ON DUPLICATE KEY UPDATE `business_id` = `business_id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `user_roles`
-- -----------------------------------------------------------------------------
INSERT INTO `user_roles` (`business_id`, `user_id`, `role_id`, `assigned_by`, `created_at`) VALUES
('cdb602cc-f3de-4dca-a626-c03fa675fa85', '86c9aaa5-e4ac-415a-ad51-d9ea01213c4c', '4ca6268c-7877-4612-a4a0-4382dc4591a0', '86c9aaa5-e4ac-415a-ad51-d9ea01213c4c', '2026-09-19 05:36:40')
ON DUPLICATE KEY UPDATE `business_id` = `business_id`;

-- -----------------------------------------------------------------------------
-- Core foundational data for `user_platform_roles`
-- -----------------------------------------------------------------------------
INSERT INTO `user_platform_roles` (`user_id`, `role_id`, `assigned_by`, `created_at`) VALUES
('e01296d3-279a-4a94-9a84-bba49e359245', 'd02bf0f3-89ca-4722-8319-480f766995ee', 'e01296d3-279a-4a94-9a84-bba49e359245', '2026-09-19 05:36:28')
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

SET FOREIGN_KEY_CHECKS = 1;
-- Setup complete.