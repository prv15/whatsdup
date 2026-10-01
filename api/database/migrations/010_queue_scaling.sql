CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key VARCHAR(190) PRIMARY KEY,
    tokens DOUBLE NOT NULL,
    last_refill DECIMAL(16, 4) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
