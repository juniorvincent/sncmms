-- ============================================================
-- Migration: role rename (staff -> user), Pending ticket status,
-- and a proper ticket_comments table (troubleshooting notes +
-- resolution comment, technician/admin only).
-- ============================================================
USE sncmms;

-- 1) Widen the role enum, migrate data, then narrow it again
--    (MySQL requires the target value to already be a valid
--    enum member before you can UPDATE rows to it).
ALTER TABLE users MODIFY role ENUM('admin','technician','staff','user') NOT NULL DEFAULT 'user';
UPDATE users SET role = 'user' WHERE role = 'staff';
ALTER TABLE users MODIFY role ENUM('admin','technician','user') NOT NULL DEFAULT 'user';

-- 2) Add 'pending' to the ticket status workflow:
--    Open (unassigned) -> Pending (assigned, not started) ->
--    In Progress -> Resolved -> Closed
ALTER TABLE tickets MODIFY status ENUM('open','pending','in_progress','resolved','closed') NOT NULL DEFAULT 'open';

-- 3) Ticket comments — technician/admin troubleshooting notes,
--    with one flagged as the resolution comment when the ticket
--    is resolved. Replaces the old single resolution_notes field
--    (that column is left in place, unused, for backward compatibility).
CREATE TABLE IF NOT EXISTS ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_resolution TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 4) One-time backfill: carry any existing resolution_notes into
--    the new comments table so nothing already recorded is lost.
INSERT INTO ticket_comments (ticket_id, user_id, comment, is_resolution, created_at)
SELECT t.id, COALESCE(t.assigned_to, t.reported_by), t.resolution_notes, 1, t.updated_at
FROM tickets t
WHERE t.resolution_notes IS NOT NULL AND t.resolution_notes != '';
