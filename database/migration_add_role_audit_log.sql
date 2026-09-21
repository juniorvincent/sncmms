-- ============================================================
-- Migration: add role_audit_log
-- Run this ONLY if you already imported database/sncmms.sql
-- before this table existed. If you're importing sncmms.sql
-- fresh, you don't need this file — it's already included.
-- ============================================================
USE sncmms;

CREATE TABLE IF NOT EXISTS role_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_user_id INT NOT NULL,
    changed_by INT NOT NULL,
    old_role VARCHAR(20) NOT NULL,
    new_role VARCHAR(20) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);
