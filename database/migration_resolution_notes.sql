-- ============================================================
-- Migration: resolution notes (technician's fault description
-- after resolving, kept for future fault prevention)
-- ============================================================
USE sncmms;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'resolution_notes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN resolution_notes TEXT NULL', 'SELECT "resolution_notes already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
