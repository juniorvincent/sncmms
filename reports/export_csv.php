<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$type = $_GET['type'] ?? 'tickets';

if ($type === 'assets') {
    $filename = 'assets_export_' . date('Y-m-d') . '.csv';
    $result = $conn->query("SELECT id, name, type, serial_number, ip_address, mac_address, location, status, purchase_date FROM assets ORDER BY name");
    $headers = ['ID', 'Name', 'Type', 'Serial Number', 'IP Address', 'MAC Address', 'Location', 'Status', 'Purchase Date'];
} else {
    $filename = 'tickets_export_' . date('Y-m-d') . '.csv';
    $result = $conn->query("
        SELECT t.id, t.reference_no, t.title, a.name AS asset_name, t.category, t.priority, t.impact,
               t.status, ru.name AS reported_by_name, tu.name AS assigned_to_name,
               t.created_at, t.updated_at, t.satisfaction, t.satisfaction_rating
        FROM tickets t
        JOIN assets a ON a.id = t.asset_id
        JOIN users ru ON ru.id = t.reported_by
        LEFT JOIN users tu ON tu.id = t.assigned_to
        ORDER BY t.created_at DESC
    ");
    $headers = ['ID', 'Reference', 'Title', 'Equipment', 'Category', 'Priority', 'Impact', 'Status', 'Reported By', 'Assigned To', 'Created', 'Updated', 'Satisfaction', 'Rating'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accented characters correctly
fputcsv($out, $headers);
while ($row = $result->fetch_assoc()) {
    fputcsv($out, $row);
}
fclose($out);
exit;
