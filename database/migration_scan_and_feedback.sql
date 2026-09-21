-- ============================================================
-- Migration: device scan fields + ticket satisfaction feedback
-- Run this if you already imported database/sncmms.sql before
-- these columns existed. If importing fresh, these are already
-- included further down in this same file where relevant tables
-- are created — but running this migration again is harmless
-- because of "IF NOT EXISTS" / column-check guards below.
-- ============================================================
USE sncmms;

-- Add IP/MAC columns to assets (for the device scanner)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'ip_address');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE assets ADD COLUMN ip_address VARCHAR(45) NULL AFTER location', 'SELECT "ip_address already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'mac_address');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE assets ADD COLUMN mac_address VARCHAR(20) NULL AFTER ip_address', 'SELECT "mac_address already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add satisfaction feedback columns to tickets
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'satisfaction');
SET @sql = IF(@col_exists = 0, "ALTER TABLE tickets ADD COLUMN satisfaction ENUM('satisfied','unsatisfied') NULL", 'SELECT "satisfaction already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'satisfaction_comment');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN satisfaction_comment TEXT NULL', 'SELECT "satisfaction_comment already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
