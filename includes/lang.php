<?php
// ============================================================
// Simple language switcher (EN / FR).
// Include this AFTER includes/auth.php (needs the session started).
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default to English if nothing chosen yet
if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'], ['en', 'fr'], true)) {
    $_SESSION['lang'] = 'en';
}

$GLOBALS['__translations'] = require __DIR__ . '/../lang/' . $_SESSION['lang'] . '.php';

/**
 * Translate a string key. Falls back to the key itself if missing,
 * so a missing translation is visible (not a blank page).
 */
function t(string $key): string {
    return $GLOBALS['__translations'][$key] ?? $key;
}
