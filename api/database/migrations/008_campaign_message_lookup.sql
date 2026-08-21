ALTER TABLE campaign_contacts
    ADD INDEX idx_campaign_contacts_meta_message (meta_message_id);
