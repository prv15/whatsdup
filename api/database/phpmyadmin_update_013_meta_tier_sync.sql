-- =============================================================================
-- WhatsdUP - phpMyAdmin Incremental Database Update (Migration 013)
-- Adds Meta verification tracking and messaging tier support
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE meta_connections
    ADD COLUMN IF NOT EXISTS business_verification_status VARCHAR(32) NOT NULL DEFAULT 'unverified' AFTER status,
    ADD COLUMN IF NOT EXISTS verification_initiated_at DATETIME NULL AFTER business_verification_status;

ALTER TABLE whatsapp_phone_numbers
    ADD COLUMN IF NOT EXISTS messaging_limit_tier VARCHAR(32) NOT NULL DEFAULT 'TIER_250' AFTER name_status;