-- ============================================================
-- Migration: Phase 1 — ticket schema expansion
-- (title, impact, location, 4-level priority, star rating)
-- ============================================================
USE sncmms;

-- Widen priority to 4 levels: low, medium, high, critical
ALTER TABLE tickets MODIFY priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium';

-- New ticket fields
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'title');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN title VARCHAR(150) NULL AFTER reference_no', 'SELECT "title already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'impact');
SET @sql = IF(@col_exists = 0, "ALTER TABLE tickets ADD COLUMN impact ENUM('low','medium','high') NOT NULL DEFAULT 'medium'", 'SELECT "impact already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'location');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN location VARCHAR(150) NULL', 'SELECT "location already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Star rating (1-5), alongside the existing satisfied/unsatisfied field
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'satisfaction_rating');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN satisfaction_rating TINYINT NULL', 'SELECT "satisfaction_rating already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: existing tickets get a title from their old description
-- (first 60 chars) so nothing displays blank after this migration.
UPDATE tickets SET title = LEFT(description, 60) WHERE title IS NULL;

-- Backfill: existing tickets get a location from their asset's location
UPDATE tickets t JOIN assets a ON a.id = t.asset_id
SET t.location = a.location
WHERE t.location IS NULL;
