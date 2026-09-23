<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['admin', 'technician']);

$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT p.*, u.name AS technician_name FROM problems p LEFT JOIN users u ON u.id = p.assigned_to WHERE p.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$problem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$problem) {
    die('Problem not found.');
}

// Related tickets
$stmt = $conn->prepare("
    SELECT t.id, t.reference_no, t.title, t.status, a.name AS asset_name
    FROM tickets t JOIN assets a ON a.id = t.asset_id
    WHERE t.problem_id = ?
    ORDER BY t.created_at DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$related_tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Related equipment — derived automatically from the related tickets' assets (distinct)
$related_equipment = [];
$seen = [];
foreach ($related_tickets as $t) {
    if (!in_array($t['asset_name'], $seen, true)) {
        $related_equipment[] = $t['asset_name'];
        $seen[] = $t['asset_name'];
    }
}

$page_title = $problem['reference_no'] . ' — ' . $problem['title'];
include __DIR__ . '/../includes/header.php';
?>

<p style="margin-bottom:14px;"><a href="list.php" style="font-size:13px; color:#2563EB;">← <?php echo t('nav_problems'); ?></a></p>

<div class="panel" style="max-width:720px; margin-bottom:20px;">
    <p style="font-size:12px; color:#94A3B8; margin-bottom:6px;"><?php echo htmlspecialchars($problem['reference_no']); ?></p>
    <h1 style="font-size:20px; color:#1E293B; margin-bottom:10px;"><?php echo htmlspecialchars($problem['title']); ?></h1>
    <p style="margin-bottom:14px; display:flex; gap:12px; flex-wrap:wrap;">
        <span class="priority-pill priority-<?php echo $problem['impact'] === 'high' ? 'critical' : $problem['impact']; ?>"><?php echo t('impact_' . $problem['impact']); ?></span>
        <span class="status-pill status-<?php echo $problem['status'] === 'investigating' ? 'pending' : $problem['status']; ?>"><?php echo t('problem_status_' . $problem['status']); ?></span>
    </p>
    <p style="color:#1E293B; margin-bottom:16px;"><?php echo nl2br(htmlspecialchars($problem['description'])); ?></p>

    <?php if ($problem['root_cause']): ?>
        <p style="font-size:12px; color:#64748B; margin-bottom:4px;"><?php echo t('root_cause'); ?></p>
        <p style="color:#1E293B; margin-bottom:16px;"><?php echo nl2br(htmlspecialchars($problem['root_cause'])); ?></p>
    <?php endif; ?>

    <?php if ($problem['solution']): ?>
        <p style="font-size:12px; color:#64748B; margin-bottom:4px;"><?php echo t('solution'); ?></p>
        <p style="color:#1E293B; margin-bottom:16px;"><?php echo nl2br(htmlspecialchars($problem['solution'])); ?></p>
    <?php endif; ?>

    <p style="font-size:13px; color:#64748B;">
        <?php echo t('assigned_to'); ?>: <?php echo $problem['technician_name'] ? htmlspecialchars($problem['technician_name']) : '—'; ?>
        <?php if ($problem['resolution_date']): ?> &nbsp;|&nbsp; <?php echo t('resolution_date'); ?>: <?php echo date('d/m/Y', strtotime($problem['resolution_date'])); ?><?php endif; ?>
    </p>

    <?php if (current_role() === 'admin'): ?>
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #E2E8F0;">
            <a href="manage.php?id=<?php echo $problem['id']; ?>" class="btn-add" style="padding:8px 16px; font-size:13px;"><?php echo t('edit'); ?></a>
        </div>
    <?php endif; ?>
</div>

<div class="panel" style="max-width:720px; margin-bottom:20px;">
    <h2><?php echo t('related_equipment'); ?></h2>
    <?php if (empty($related_equipment)): ?>
        <p style="font-size:13px; color:#64748B;">—</p>
    <?php else: ?>
        <p style="font-size:13px; color:#1E293B;"><?php echo htmlspecialchars(implode(', ', $related_equipment)); ?></p>
    <?php endif; ?>
</div>

<div class="panel" style="max-width:720px;">
    <h2><?php echo t('related_tickets'); ?> (<?php echo count($related_tickets); ?>)</h2>
    <?php if (empty($related_tickets)): ?>
        <p style="font-size:13px; color:#64748B;"><?php echo t('no_tickets_linked'); ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?php echo t('reference'); ?></th><th><?php echo t('ticket_title'); ?></th><th><?php echo t('nav_assets'); ?></th><th><?php echo t('status'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($related_tickets as $t): ?>
                    <tr>
                        <td><a href="/sncmms/tickets/update.php?id=<?php echo $t['id']; ?>" style="color:#2563EB;"><?php echo htmlspecialchars($t['reference_no'] ?? ('#'.$t['id'])); ?></a></td>
                        <td><?php echo htmlspecialchars($t['title'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['asset_name']); ?></td>
                        <td><span class="status-pill status-<?php echo $t['status']; ?>"><?php echo t('status_' . $t['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
