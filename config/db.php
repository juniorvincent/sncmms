<?php
// ============================================================
// Database connection
// Update these values to match your local MySQL/XAMPP setup.
// ============================================================

$DB_HOST = 'localhost';
$DB_NAME = 'sncmms_db';
$DB_USER = 'root';
$DB_PASS = '';        // default XAMPP MySQL password is blank

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
