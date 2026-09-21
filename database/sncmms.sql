-- ============================================================
-- Smart Network and Computer Maintenance Management System
-- Database Schema
-- Import this file via phpMyAdmin: Create DB "sncmms" -> Import
-- ============================================================

CREATE DATABASE IF NOT EXISTS sncmms;
USE sncmms;

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
