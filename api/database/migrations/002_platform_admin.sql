CREATE TABLE user_platform_roles (
    user_id CHAR(36) NOT NULL,
    role_id CHAR(36) NOT NULL,
    assigned_by CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_platform_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_platform_roles_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_platform_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_platform_roles_role (role_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
