-- ============================================================
-- Migration: Phase 3 — Problem Management
-- A "Problem" is a root cause that multiple tickets can be
-- linked to (e.g. several "Wi-Fi is slow" tickets -> one
-- Problem #P001 "Network Congestion").
-- ============================================================
USE sncmms;

CREATE TABLE IF NOT EXISTS problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    root_cause TEXT NULL,
    solution TEXT NULL,
    impact ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT NULL,
    created_by INT NULL,
    resolution_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Link tickets to a problem (one problem -> many tickets)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sncmms' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'problem_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE tickets ADD COLUMN problem_id INT NULL, ADD FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE SET NULL', 'SELECT "problem_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
