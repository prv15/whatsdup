-- =============================================================================
-- WhatsdUP - phpMyAdmin Incremental Database Update (Migration 012)
-- Enables 'pending' status for templates submitted to Meta Cloud API
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE message_templates
    MODIFY status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft';