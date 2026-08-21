ALTER TABLE message_templates
    ADD COLUMN header_type ENUM('none','image') NOT NULL DEFAULT 'none' AFTER category,
    ADD COLUMN header_media_url VARCHAR(500) NULL AFTER header_type;
