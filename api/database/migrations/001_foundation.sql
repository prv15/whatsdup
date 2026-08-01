CREATE TABLE IF NOT EXISTS migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE resellers (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    INDEX idx_resellers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE businesses (
    id CHAR(36) PRIMARY KEY,
    reseller_id CHAR(36) NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    legal_name VARCHAR(190) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    language VARCHAR(12) NOT NULL DEFAULT 'en',
    default_country_code VARCHAR(8) NULL,
    status ENUM('pending','active','suspended','archived') NOT NULL DEFAULT 'pending',
    access_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_businesses_reseller FOREIGN KEY (reseller_id) REFERENCES resellers(id),
    INDEX idx_businesses_owner_status (reseller_id, status),
    INDEX idx_businesses_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    status ENUM('invited','active','suspended') NOT NULL DEFAULT 'invited',
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    INDEX idx_users_status (status),
    INDEX idx_users_lock (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_users (
    business_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    status ENUM('invited','active','suspended') NOT NULL DEFAULT 'invited',
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    joined_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (business_id, user_id),
    CONSTRAINT fk_business_users_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_business_users_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_business_users_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reseller_users (
    reseller_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    role_key VARCHAR(64) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (reseller_id, user_id),
    CONSTRAINT fk_reseller_users_reseller FOREIGN KEY (reseller_id) REFERENCES resellers(id),
    CONSTRAINT fk_reseller_users_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reseller_branding (
    reseller_id CHAR(36) PRIMARY KEY,
    settings JSON NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_reseller_branding_reseller FOREIGN KEY (reseller_id) REFERENCES resellers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reseller_domains (
    id CHAR(36) PRIMARY KEY,
    reseller_id CHAR(36) NOT NULL,
    hostname VARCHAR(253) NOT NULL UNIQUE,
    status ENUM('pending','verified','failed') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_reseller_domains_reseller FOREIGN KEY (reseller_id) REFERENCES resellers(id),
    INDEX idx_reseller_domains_owner (reseller_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    scope ENUM('platform','reseller','business') NOT NULL,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_roles_name_scope (name, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    business_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    role_id CHAR(36) NOT NULL,
    assigned_by CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (business_id, user_id, role_id),
    CONSTRAINT fk_user_roles_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_user_roles_user_business (user_id, business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token_family CHAR(36) NOT NULL,
    refresh_token_hash CHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user_active (user_id, revoked_at, expires_at),
    INDEX idx_sessions_family (token_family)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_verifications_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_resets_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NULL,
    user_id CHAR(36) NULL,
    action VARCHAR(150) NOT NULL,
    subject_type VARCHAR(100) NOT NULL,
    subject_id CHAR(36) NULL,
    metadata JSON NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_audit_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_audit_tenant_time (business_id, created_at),
    INDEX idx_audit_subject (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
    setting_key VARCHAR(190) PRIMARY KEY,
    value JSON NOT NULL,
    is_secret BOOLEAN NOT NULL DEFAULT FALSE,
    updated_by CHAR(36) NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_system_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(80) NOT NULL UNIQUE,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    limits JSON NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plan_features (
    plan_id CHAR(36) NOT NULL,
    feature_key VARCHAR(120) NOT NULL,
    value JSON NOT NULL,
    PRIMARY KEY (plan_id, feature_key),
    CONSTRAINT fk_plan_features_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    plan_id CHAR(36) NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'manual',
    status ENUM('trialing','active','past_due','suspended','cancelled','expired') NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_subscriptions_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_subscriptions_business_status (business_id, status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_usage (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    subscription_id CHAR(36) NOT NULL,
    metric_key VARCHAR(120) NOT NULL,
    period_start DATETIME NOT NULL,
    period_end DATETIME NOT NULL,
    quantity BIGINT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_usage_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_usage_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    UNIQUE KEY uq_usage_period (business_id, subscription_id, metric_key, period_start),
    INDEX idx_usage_tenant_period (business_id, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_logs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NULL,
    event_type VARCHAR(120) NOT NULL,
    identifier VARCHAR(254) NULL,
    ip_address VARCHAR(45) NOT NULL,
    metadata JSON NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_security_logs_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_security_event_time (event_type, created_at),
    INDEX idx_security_identifier_time (identifier, created_at),
    INDEX idx_security_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE queue_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id CHAR(36) NULL,
    queue VARCHAR(80) NOT NULL,
    job_type VARCHAR(190) NOT NULL,
    payload JSON NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    trace_id CHAR(36) NOT NULL,
    status ENUM('ready','reserved','completed','failed','cancelled') NOT NULL DEFAULT 'ready',
    priority SMALLINT NOT NULL DEFAULT 100,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    lock_expires_at DATETIME NULL,
    lock_token CHAR(36) NULL,
    completed_at DATETIME NULL,
    last_error_code VARCHAR(120) NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_queue_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    UNIQUE KEY uq_queue_idempotency (queue, idempotency_key),
    INDEX idx_queue_claim (queue, status, available_at, priority, id),
    INDEX idx_queue_stale (status, lock_expires_at),
    INDEX idx_queue_tenant (business_id, queue, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_job_id BIGINT UNSIGNED NOT NULL,
    business_id CHAR(36) NULL,
    queue VARCHAR(80) NOT NULL,
    job_type VARCHAR(190) NOT NULL,
    payload JSON NOT NULL,
    idempotency_key VARCHAR(190) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL,
    error_code VARCHAR(120) NULL,
    error_message TEXT NOT NULL,
    failed_at DATETIME NOT NULL,
    retried_at DATETIME NULL,
    CONSTRAINT fk_failed_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    INDEX idx_failed_queue_time (queue, failed_at),
    INDEX idx_failed_tenant_time (business_id, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduled_jobs (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NULL,
    job_key VARCHAR(190) NOT NULL,
    payload JSON NOT NULL,
    due_at DATETIME NOT NULL,
    status ENUM('scheduled','dispatching','dispatched','cancelled','failed') NOT NULL DEFAULT 'scheduled',
    lock_token CHAR(36) NULL,
    lock_expires_at DATETIME NULL,
    dispatched_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_scheduled_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    UNIQUE KEY uq_scheduled_job (business_id, job_key),
    INDEX idx_scheduled_due (status, due_at),
    INDEX idx_scheduled_stale (status, lock_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
