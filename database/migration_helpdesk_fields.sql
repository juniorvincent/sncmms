-- ============================================================
-- Migration: help-desk style ticket fields
-- (reference number, category, SLA due date)
-- Run this if you already imported database/sncmms.sql before
-- these columns existed.
-- ============================================================
USE sncmms;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'reference_no');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN reference_no VARCHAR(20) NULL UNIQUE AFTER id', 'SELECT "reference_no already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'category');
SET @sql = IF(@col_exists = 0, "ALTER TABLE tickets ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'other'", 'SELECT "category already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'sla_due_at');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN sla_due_at DATETIME NULL', 'SELECT "sla_due_at already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- If you ran an earlier version of this migration, category values may have
-- been stored in French text — convert them to the neutral codes now used
-- by the translation system (t('category_...')).
UPDATE tickets SET category = 'other'    WHERE category = 'Autre';
UPDATE tickets SET category = 'hardware' WHERE category = 'Matériel informatique';
UPDATE tickets SET category = 'network'  WHERE category = 'Réseau';
UPDATE tickets SET category = 'printer'  WHERE category = 'Imprimante';
UPDATE tickets SET category = 'software' WHERE category = 'Logiciel';
