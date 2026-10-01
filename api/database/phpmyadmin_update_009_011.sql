-- =============================================================================
-- WhatsdUP - phpMyAdmin Incremental Database Update (Migrations 009 - 011)
-- Safe to run on existing databases already on Migration 008 or earlier.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 009: Pipeline Hardening
-- -----------------------------------------------------------------------------
ALTER TABLE campaigns
    MODIFY status ENUM('draft','scheduled','queued','processing','dispatched','completed','paused','cancelled','failed') NOT NULL DEFAULT 'draft',
    ADD COLUMN IF NOT EXISTS variable_mappings JSON NULL AFTER template_id;

ALTER TABLE message_templates
    ADD COLUMN IF NOT EXISTS variables JSON NULL AFTER body;

ALTER TABLE campaign_contacts
    MODIFY status ENUM('queued','accepted','sent','delivered','read','failed','skipped') NOT NULL DEFAULT 'queued';

CREATE TABLE IF NOT EXISTS webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id CHAR(36) NULL,
    event_type VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL UNIQUE,
    payload JSON NOT NULL,
    status ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
    error_message VARCHAR(500) NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_webhook_events_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    INDEX idx_webhook_events_status (status, created_at),
    INDEX idx_webhook_events_tenant (business_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 010: Rate Limiting & Queue Scaling
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key VARCHAR(190) PRIMARY KEY,
    tokens DOUBLE NOT NULL,
    last_refill DECIMAL(16, 4) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 011: Conversations & Team Inbox
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    contact_id CHAR(36) NOT NULL,
    meta_phone_number_id VARCHAR(64) NULL,
    status ENUM('open','closed','snoozed') NOT NULL DEFAULT 'open',
    last_message_at DATETIME NOT NULL,
    window_expires_at DATETIME NULL,
    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
    assigned_user_id CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_conversations_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_conversations_contact FOREIGN KEY (contact_id) REFERENCES contacts(id),
    CONSTRAINT fk_conversations_assignee FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_conversations_business_contact (business_id, contact_id),
    INDEX idx_conversations_tenant_status (business_id, status, last_message_at),
    INDEX idx_conversations_tenant_assigned (business_id, assigned_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_messages (
    id CHAR(36) PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    business_id CHAR(36) NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    sender_user_id CHAR(36) NULL,
    message_type ENUM('text','image','document','template','interactive') NOT NULL DEFAULT 'text',
    content TEXT NOT NULL,
    media_url VARCHAR(500) NULL,
    meta_message_id VARCHAR(128) NULL,
    status ENUM('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_conv_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_messages_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_conv_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_conv_messages_thread (conversation_id, created_at),
    INDEX idx_conv_messages_meta (meta_message_id),
    INDEX idx_conv_messages_tenant (business_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions for Inbox
INSERT IGNORE INTO permissions (id, name, created_at)
VALUES
    (UUID(), 'inbox.view', UTC_TIMESTAMP()),
    (UUID(), 'inbox.send', UTC_TIMESTAMP()),
    (UUID(), 'inbox.assign', UTC_TIMESTAMP());

-- Grant inbox permissions to roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name IN ('Business Owner', 'Business Admin', 'Super Admin')
  AND p.name IN ('inbox.view', 'inbox.send', 'inbox.assign');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'Campaign Manager'
  AND p.name IN ('inbox.view', 'inbox.send');

-- Record in migrations table so console stays in sync
INSERT IGNORE INTO migrations (migration, executed_at) VALUES
('009_pipeline_hardening.sql', UTC_TIMESTAMP()),
('010_queue_scaling.sql', UTC_TIMESTAMP()),
('011_conversations_inbox.sql', UTC_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
-- Incremental update complete!
