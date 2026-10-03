ALTER TABLE message_templates
    MODIFY status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft';