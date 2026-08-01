ALTER TABLE plans
    ADD COLUMN description VARCHAR(500) NOT NULL DEFAULT '' AFTER code,
    ADD COLUMN price_minor INT UNSIGNED NULL AFTER description,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER price_minor,
    ADD COLUMN billing_interval ENUM('month','year','custom') NOT NULL DEFAULT 'month' AFTER currency,
    ADD COLUMN is_public BOOLEAN NOT NULL DEFAULT TRUE AFTER status,
    ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_public;

INSERT INTO plans (id, name, code, description, price_minor, currency, billing_interval, status, is_public, sort_order, limits, created_at, updated_at)
VALUES
    (UUID(), 'Launch', 'launch', 'For small businesses beginning with structured WhatsApp campaigns.', 99900, 'INR', 'month', 'active', TRUE, 10, JSON_OBJECT('phoneNumbers', 1, 'teamMembers', 2, 'contacts', 5000, 'monthlyRecipients', 10000), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    (UUID(), 'Growth', 'growth', 'For growing teams running regular campaigns across larger audiences.', 249900, 'INR', 'month', 'active', TRUE, 20, JSON_OBJECT('phoneNumbers', 2, 'teamMembers', 5, 'contacts', 25000, 'monthlyRecipients', 75000), UTC_TIMESTAMP(), UTC_TIMESTAMP()),
    (UUID(), 'Scale', 'scale', 'For established operations needing higher limits and closer support.', NULL, 'INR', 'custom', 'active', TRUE, 30, JSON_OBJECT('phoneNumbers', NULL, 'teamMembers', NULL, 'contacts', NULL, 'monthlyRecipients', NULL), UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE code = VALUES(code);

INSERT INTO plan_features (plan_id, feature_key, value)
SELECT id, 'feature_01', JSON_QUOTE('Contact import and groups') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Template sync and campaigns') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Delivery and read reporting') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Email support') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_01', JSON_QUOTE('Everything in Launch') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Tags and custom contact fields') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Advanced campaign reporting') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Priority email support') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_01', JSON_QUOTE('Everything in Growth') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Roles and permission controls') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Audit and operational exports') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Guided onboarding') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_05', JSON_QUOTE('Dedicated priority support') FROM plans WHERE code = 'scale'
ON DUPLICATE KEY UPDATE value = VALUES(value);
