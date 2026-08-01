CREATE TABLE encrypted_tokens (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    provider VARCHAR(40) NOT NULL,
    ciphertext LONGTEXT NOT NULL,
    nonce VARCHAR(255) NOT NULL,
    key_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    metadata JSON NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_encrypted_tokens_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    INDEX idx_encrypted_tokens_tenant_provider (business_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta_connections (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    token_id CHAR(36) NULL,
    meta_business_id VARCHAR(64) NULL,
    app_id VARCHAR(64) NOT NULL,
    status ENUM('not_connected','connecting','connected','action_required','verification_required','token_invalid','restricted','disconnected','webhook_error') NOT NULL DEFAULT 'not_connected',
    connected_by CHAR(36) NULL,
    connected_at DATETIME NULL,
    last_synced_at DATETIME NULL,
    last_tested_at DATETIME NULL,
    last_error_code VARCHAR(120) NULL,
    last_error_message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_meta_connections_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_meta_connections_token FOREIGN KEY (token_id) REFERENCES encrypted_tokens(id),
    CONSTRAINT fk_meta_connections_user FOREIGN KEY (connected_by) REFERENCES users(id),
    UNIQUE KEY uq_meta_connection_business (business_id),
    INDEX idx_meta_connections_status (status, last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waba_accounts (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    meta_connection_id CHAR(36) NOT NULL,
    meta_waba_id VARCHAR(64) NOT NULL,
    name VARCHAR(190) NULL,
    currency VARCHAR(12) NULL,
    timezone_id VARCHAR(64) NULL,
    review_status VARCHAR(64) NULL,
    status VARCHAR(64) NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_waba_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_waba_connection FOREIGN KEY (meta_connection_id) REFERENCES meta_connections(id),
    UNIQUE KEY uq_waba_meta_id (meta_waba_id),
    INDEX idx_waba_tenant_status (business_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_phone_numbers (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    waba_account_id CHAR(36) NOT NULL,
    meta_phone_number_id VARCHAR(64) NOT NULL,
    display_phone_number VARCHAR(40) NULL,
    verified_name VARCHAR(190) NULL,
    quality_rating VARCHAR(40) NULL,
    name_status VARCHAR(64) NULL,
    registration_status VARCHAR(64) NULL,
    connection_status VARCHAR(64) NOT NULL DEFAULT 'connected',
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    last_synced_at DATETIME NULL,
    last_message_at DATETIME NULL,
    last_webhook_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_phone_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_phone_waba FOREIGN KEY (waba_account_id) REFERENCES waba_accounts(id),
    UNIQUE KEY uq_phone_meta_id (meta_phone_number_id),
    INDEX idx_phone_tenant_status (business_id, connection_status, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_subscriptions (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    waba_account_id CHAR(36) NOT NULL,
    status ENUM('pending','active','failed','disconnected') NOT NULL DEFAULT 'pending',
    subscribed_at DATETIME NULL,
    last_verified_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_webhook_sub_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_webhook_sub_waba FOREIGN KEY (waba_account_id) REFERENCES waba_accounts(id),
    UNIQUE KEY uq_webhook_waba (waba_account_id),
    INDEX idx_webhook_sub_tenant_status (business_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta_api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id CHAR(36) NULL,
    correlation_id CHAR(36) NOT NULL,
    operation VARCHAR(120) NOT NULL,
    graph_version VARCHAR(16) NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    duration_ms INT UNSIGNED NULL,
    success BOOLEAN NOT NULL,
    error_code VARCHAR(120) NULL,
    error_message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_meta_logs_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    INDEX idx_meta_logs_tenant_time (business_id, created_at),
    INDEX idx_meta_logs_operation_status (operation, success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
