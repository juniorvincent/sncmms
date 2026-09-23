-- ============================================================
-- Smart Network and Computer Maintenance Management System
-- Database Schema
-- Import this file via phpMyAdmin: Create DB "sncmms" -> Import
-- ============================================================


-- ---------------------------------
-- Users (Admin / Technician / Staff)
-- ---------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'technician', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------
-- Assets (computers, network devices)
-- ---------------------------------
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(50) NOT NULL,          -- e.g. Computer, Router, Printer, Switch
    serial_number VARCHAR(100),
    location VARCHAR(150),
    ip_address VARCHAR(45),
    mac_address VARCHAR(20),
    status ENUM('active', 'faulty', 'retired') NOT NULL DEFAULT 'active',
    purchase_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------
-- Tickets (maintenance requests)
-- ---------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) UNIQUE,
    title VARCHAR(150) NULL,
    asset_id INT NOT NULL,
    reported_by INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    description TEXT NOT NULL,
    location VARCHAR(150) NULL,
    priority ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    impact ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    status ENUM('open', 'pending', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    satisfaction ENUM('satisfied', 'unsatisfied') NULL,
    satisfaction_rating TINYINT NULL,
    satisfaction_comment TEXT NULL,
    resolution_notes TEXT NULL,
    sla_due_at DATETIME NULL,
    problem_id INT NULL,
    sla_breach_notified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ---------------------------------
-- Preventive Maintenance Schedule
-- ---------------------------------
CREATE TABLE IF NOT EXISTS maintenance_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    frequency_months INT NOT NULL DEFAULT 3,
    next_due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

-- ---------------------------------
-- Ticket history log (for audit trail)
-- ---------------------------------
CREATE TABLE IF NOT EXISTS ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    changed_by INT NOT NULL,
    change_note VARCHAR(255) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- ---------------------------------
-- Ticket comments (technician/admin troubleshooting notes;
-- one comment is flagged as the resolution comment)
-- ---------------------------------
-- ---------------------------------
-- Knowledge Base articles
-- ---------------------------------
-- ---------------------------------
-- Problems (root-cause grouping for multiple related tickets)
-- ---------------------------------
-- ---------------------------------
-- Notifications
-- ---------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------
-- Ticket attachments
-- ---------------------------------
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

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

-- Now that problems exists, link tickets.problem_id to it
ALTER TABLE tickets ADD FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS kb_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    content TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

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

-- ---------------------------------
-- Role change audit log
-- (matches the Admin-only role-change flowchart)
-- ---------------------------------
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

-- ---------------------------------
-- Sample seed data (for testing)
-- NOTE: No admin user is seeded here on purpose — passwords must be
-- hashed with PHP's password_hash(), not guessed/hardcoded in SQL.
-- Run setup/create_admin.php once after import to create your first
-- admin account safely, then delete that script.
-- ---------------------------------
INSERT INTO assets (name, type, serial_number, location, status, purchase_date) VALUES
('PC-A14', 'Computer', 'SN-PC-001', 'Room A', 'active', '2024-01-15'),
('Router-B2', 'Network', 'SN-RT-002', 'Server Room', 'active', '2023-11-01'),
('Printer-3F', 'Printer', 'SN-PR-003', '3rd Floor', 'faulty', '2022-06-20');

INSERT INTO kb_articles (title, category, content) VALUES
('How to reset a router', 'network', 'Unplug the router from power. Wait 10 seconds. Locate the small reset button (usually recessed) on the back of the unit. Press and hold it for 10 seconds using a pin while plugging the power back in. Wait 2-3 minutes for the router to fully restart. Note: this restores factory settings, so any custom Wi-Fi name/password will need to be reconfigured.'),
('How to configure a switch', 'network', 'Connect to the switch via its management IP using a browser, or via console cable for unmanaged setup. Log in with the default credentials (check the device label). Assign VLANs if needed. Set port speed/duplex to Auto unless a device requires a fixed setting. Save the configuration and, if supported, back up the config file before making further changes.'),
('How to troubleshoot Wi-Fi', 'network', '1) Confirm the device''s Wi-Fi is turned on. 2) Check signal strength — move closer to the access point. 3) Restart the affected device. 4) Restart the router/access point. 5) Check if other devices have the same issue (isolates device vs network problem). 6) Verify the correct Wi-Fi password is being used. 7) If only one device is affected, forget the network on that device and reconnect.'),
('How to solve DHCP problems', 'network', 'If a device isn''t getting an IP address: 1) Restart the device''s network adapter. 2) Check the DHCP server (router) is running and has free addresses in its pool. 3) Release/renew the IP (ipconfig /release then /renew on Windows). 4) Check for IP address conflicts. 5) Confirm the DHCP scope hasn''t been exhausted — expand the pool range if needed.'),
('How to check network connectivity', 'network', 'Use ping to test connectivity: ping 8.8.8.8 tests general internet access; ping the default gateway tests local network access. Use tracert (Windows) or traceroute (Linux/Mac) to see where a connection fails along the path. Check physical cabling and link lights on network ports as a first step.'),
('Common printer problems', 'printer', 'Printer not responding: check it''s powered on and connected (USB/network). Paper jams: open all access panels and remove torn paper fully. Poor print quality: run the built-in cleaning cycle and check ink/toner levels. Printer shows offline: restart the print spooler service, or remove and re-add the printer in the OS settings.'),
('Basic network troubleshooting', 'network', 'Follow this order: 1) Check physical connections (cables, power). 2) Check link/activity lights on network equipment. 3) Restart the affected device. 4) Test with ping to isolate whether it''s a local or wider network issue. 5) Check if the problem affects one user or many — many users pointing to a shared device (switch/router) failing. 6) Escalate to Admin if the issue is at the router/core switch level.');
