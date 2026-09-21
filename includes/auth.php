<?php
// ============================================================
// Session + role-access helpers.
// Include this at the TOP of every protected page.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /sncmms/auth/login.php');
        exit;
    }
}

// Restrict a page to specific roles, e.g. require_role(['admin'])
function require_role(array $allowed_roles) {
    require_login();
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        http_response_code(403);
        die('Access denied: you do not have permission to view this page.');
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_role() {
    return $_SESSION['role'] ?? null;
}
