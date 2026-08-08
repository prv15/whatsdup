ALTER TABLE plans ADD COLUMN annual_price_minor INT UNSIGNED NULL AFTER price_minor;

UPDATE plans SET price_minor = 99900, annual_price_minor = 958800, billing_interval = 'month', limits = JSON_OBJECT('phoneNumbers', 1, 'teamMembers', 3, 'contacts', 5000, 'monthlyRecipients', 12000), updated_at = UTC_TIMESTAMP() WHERE code = 'launch';
UPDATE plans SET price_minor = 229900, annual_price_minor = 2206800, billing_interval = 'month', limits = JSON_OBJECT('phoneNumbers', 2, 'teamMembers', 10, 'contacts', 25000, 'monthlyRecipients', 100000), updated_at = UTC_TIMESTAMP() WHERE code = 'growth';
UPDATE plans SET price_minor = NULL, annual_price_minor = NULL, billing_interval = 'custom', updated_at = UTC_TIMESTAMP() WHERE code = 'scale';

DELETE FROM plan_features WHERE plan_id IN (SELECT id FROM plans WHERE code IN ('launch','growth','scale'));

INSERT INTO plan_features (plan_id, feature_key, value)
SELECT id, 'feature_01', JSON_QUOTE('Unlimited campaign creation') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Official Cloud API with 0% markup') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Template sync and approval status') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Scheduled campaign sending') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_05', JSON_QUOTE('Consent and opt-out suppression') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_06', JSON_QUOTE('Delivery and read reporting') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_07', JSON_QUOTE('CSV contact imports') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_08', JSON_QUOTE('Email support') FROM plans WHERE code = 'launch'
UNION ALL SELECT id, 'feature_01', JSON_QUOTE('Everything in Launch') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Tags, groups and custom fields') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Advanced campaign analytics') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Recipient-level status history') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_05', JSON_QUOTE('Failure diagnostics and safe retries') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_06', JSON_QUOTE('API and webhook access') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_07', JSON_QUOTE('Audit activity history') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_08', JSON_QUOTE('Priority support') FROM plans WHERE code = 'growth'
UNION ALL SELECT id, 'feature_01', JSON_QUOTE('Everything in Growth') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_02', JSON_QUOTE('Advanced roles and permissions') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_03', JSON_QUOTE('Operational and audit exports') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_04', JSON_QUOTE('Custom API integration support') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_05', JSON_QUOTE('Guided Meta onboarding') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_06', JSON_QUOTE('Migration and launch assistance') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_07', JSON_QUOTE('Priority issue escalation') FROM plans WHERE code = 'scale'
UNION ALL SELECT id, 'feature_08', JSON_QUOTE('Dedicated success contact') FROM plans WHERE code = 'scale';
