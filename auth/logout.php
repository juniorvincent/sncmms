<?php
require_once __DIR__ . '/../includes/auth.php';

$_SESSION = [];
session_destroy();

header('Location: /sncmms/auth/login.php');
exit;
