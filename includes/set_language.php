<?php
require_once __DIR__ . '/lang.php';

$lang = $_GET['lang'] ?? 'en';
if (in_array($lang, ['en', 'fr'], true)) {
    $_SESSION['lang'] = $lang;
}

// Return to the page the user came from, or the homepage
$referer = $_SERVER['HTTP_REFERER'] ?? '/sncmms/';
header('Location: ' . $referer);
exit;
