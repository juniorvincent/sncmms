<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$attachment_id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, t.reported_by, t.assigned_to
    FROM attachments a JOIN tickets t ON t.id = a.ticket_id
    WHERE a.id = ?
");
$stmt->bind_param('i', $attachment_id);
$stmt->execute();
$att = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$att) {
    die('Attachment not found.');
}

// Access: Admin, the assigned technician, or the ticket's reporter
$uid = (int) current_user_id();
$allowed = current_role() === 'admin' || (int) $att['assigned_to'] === $uid || (int) $att['reported_by'] === $uid;
if (!$allowed) {
    http_response_code(403);
    die('You do not have access to this file.');
}

$path = __DIR__ . '/../uploads/' . $att['stored_name'];
if (!file_exists($path)) {
    die('File missing on server.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($att['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
