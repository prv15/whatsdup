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

INSERT IGNORE INTO permissions (id, name, description, created_at)
VALUES
    (UUID(), 'inbox.view', 'View team inbox conversations and message history', UTC_TIMESTAMP()),
    (UUID(), 'inbox.send', 'Send outbound two-way messages in team inbox', UTC_TIMESTAMP()),
    (UUID(), 'inbox.assign', 'Assign conversations to team members', UTC_TIMESTAMP());

-- Grant inbox permissions to Business Owner, Business Admin, Campaign Manager, and Super Admin
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
