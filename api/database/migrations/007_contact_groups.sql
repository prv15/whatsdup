CREATE TABLE contact_groups (
    id CHAR(36) PRIMARY KEY,
    business_id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(300) NULL,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_contact_groups_business FOREIGN KEY (business_id) REFERENCES businesses(id),
    CONSTRAINT fk_contact_groups_creator FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY uq_contact_groups_name (business_id, name),
    INDEX idx_contact_groups_tenant (business_id, deleted_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_group_members (
    group_id CHAR(36) NOT NULL,
    contact_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (group_id, contact_id),
    CONSTRAINT fk_contact_group_members_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_contact_group_members_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    INDEX idx_contact_group_members_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE campaigns MODIFY audience_type ENUM('all_opted_in','selected','groups') NOT NULL DEFAULT 'all_opted_in';

CREATE TABLE campaign_groups (
    campaign_id CHAR(36) NOT NULL,
    group_id CHAR(36) NOT NULL,
    PRIMARY KEY (campaign_id, group_id),
    CONSTRAINT fk_campaign_groups_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_groups_group FOREIGN KEY (group_id) REFERENCES contact_groups(id),
    INDEX idx_campaign_groups_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
