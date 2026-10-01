ALTER TABLE campaigns
    MODIFY status ENUM('draft','scheduled','queued','processing','dispatched','completed','paused','cancelled','failed') NOT NULL DEFAULT 'draft',
    ADD COLUMN variable_mappings JSON NULL AFTER template_id;

ALTER TABLE message_templates
    ADD COLUMN variables JSON NULL AFTER body;

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
