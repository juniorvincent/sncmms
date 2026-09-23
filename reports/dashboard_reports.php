<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['admin']); // Only Admin has "View reports and statistics" per the permission spec

// --- Ticket counts by status ---
$by_status = $conn->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status");

// --- Tickets by priority ---
$by_priority = $conn->query("SELECT priority, COUNT(*) AS c FROM tickets GROUP BY priority");

// --- Tickets by category ---
$by_category = $conn->query("SELECT category, COUNT(*) AS c FROM tickets GROUP BY category ORDER BY c DESC");

// --- Tickets by technician ---
$by_technician = $conn->query("
    SELECT u.name, COUNT(t.id) AS c
    FROM users u LEFT JOIN tickets t ON t.assigned_to = u.id
    WHERE u.role = 'technician'
    GROUP BY u.id, u.name
    ORDER BY c DESC
");

// --- Average resolution time (created_at -> updated_at, for resolved/closed tickets) ---
$avg_res = $conn->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) AS avg_hours, COUNT(*) AS n
    FROM tickets WHERE status IN ('resolved','closed')
")->fetch_assoc();

// --- SLA breaches ---
$sla_breaches = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE sla_breach_notified = 1")->fetch_assoc()['c'];

// --- Equipment failures (currently faulty) ---
$equipment_failures = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status = 'faulty'")->fetch_assoc()['c'];

// --- Satisfaction summary ---
$sat_satisfied = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE satisfaction = 'satisfied'")->fetch_assoc()['c'];
$sat_unsatisfied = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE satisfaction = 'unsatisfied'")->fetch_assoc()['c'];
$avg_rating_row = $conn->query("SELECT AVG(satisfaction_rating) AS avg_r FROM tickets WHERE satisfaction_rating IS NOT NULL")->fetch_assoc();

// --- Maintenance schedule snapshot ---
// Note: this project tracks *scheduled* preventive maintenance only —
// there is no separate corrective-maintenance log table yet, so
// "maintenance history" below reflects the schedule, not completed work orders.
$maintenance_overdue = $conn->query("SELECT COUNT(*) AS c FROM maintenance_schedule WHERE next_due_date < CURDATE()")->fetch_assoc()['c'];
$maintenance_upcoming = $conn->query("SELECT COUNT(*) AS c FROM maintenance_schedule WHERE next_due_date >= CURDATE()")->fetch_assoc()['c'];

// --- Predictive maintenance flag (Week 5 PoC) ---
$at_risk = get_at_risk_assets($conn, 90, 3);

// --- Resolution history: technician's resolution comments + user satisfaction,
//     kept together per asset so patterns can inform future fault prevention.
//     (Reads from ticket_comments/is_resolution — the old resolution_notes
//     column is no longer written to since the comment-thread system replaced it.) ---
$resolution_history = $conn->query("
    SELECT t.reference_no, c.comment AS resolution_comment, t.satisfaction, t.satisfaction_comment,
           c.created_at, a.name AS asset_name
    FROM ticket_comments c
    JOIN tickets t ON t.id = c.ticket_id
    JOIN assets a ON a.id = t.asset_id
    WHERE c.is_resolution = 1
    ORDER BY c.created_at DESC
    LIMIT 15
");

$page_title = t('nav_reports');
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div class="lang-switch" style="margin:0;">
        <a href="export_csv.php?type=tickets" style="color:#2563EB; font-size:13px; text-decoration:none;">⬇ <?php echo t('export_tickets_csv'); ?></a>
        <span>|</span>
        <a href="export_csv.php?type=assets" style="color:#2563EB; font-size:13px; text-decoration:none;">⬇ <?php echo t('export_assets_csv'); ?></a>
        <span>|</span>
        <a href="javascript:window.print()" style="color:#2563EB; font-size:13px; text-decoration:none;">🖨 <?php echo t('print_save_pdf'); ?></a>
    </div>
</div>

<!-- Summary numbers -->
<div class="cards no-print" style="flex-wrap:wrap; margin-bottom:20px;">
    <div class="card total"><h3><?php echo t('avg_resolution_time'); ?></h3><div class="value" style="font-size:20px;"><?php echo $avg_res['n'] > 0 ? round($avg_res['avg_hours'], 1) . 'h' : '—'; ?></div></div>
    <div class="card offline"><h3><?php echo t('sla_breaches'); ?></h3><div class="value"><?php echo $sla_breaches; ?></div></div>
    <div class="card offline"><h3><?php echo t('equipment_failures'); ?></h3><div class="value"><?php echo $equipment_failures; ?></div></div>
    <div class="card" style="background:#F59E0B;"><h3><?php echo t('maintenance_overdue'); ?></h3><div class="value"><?php echo $maintenance_overdue; ?></div></div>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom:20px;">
    <div class="panel">
        <h2><?php echo t('report_tickets_by_status'); ?></h2>
        <table class="data-table">
            <thead><tr><th><?php echo t('status'); ?></th><th><?php echo t('count'); ?></th></tr></thead>
            <tbody>
                <?php if ($by_status->num_rows === 0): ?>
                    <tr><td colspan="2"><?php echo t('no_ticket_data'); ?></td></tr>
                <?php else: ?>
                    <?php while ($row = $by_status->fetch_assoc()): ?>
                        <tr><td><span class="status-pill status-<?php echo $row['status']; ?>"><?php echo t('status_' . $row['status']); ?></span></td><td><?php echo $row['c']; ?></td></tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2><?php echo t('report_tickets_by_priority'); ?></h2>
        <table class="data-table">
            <thead><tr><th><?php echo t('priority'); ?></th><th><?php echo t('count'); ?></th></tr></thead>
            <tbody>
                <?php if ($by_priority->num_rows === 0): ?>
                    <tr><td colspan="2"><?php echo t('no_ticket_data'); ?></td></tr>
                <?php else: ?>
                    <?php while ($row = $by_priority->fetch_assoc()): ?>
                        <tr><td><span class="priority-pill priority-<?php echo $row['priority']; ?>"><?php echo t('priority_' . $row['priority']); ?></span></td><td><?php echo $row['c']; ?></td></tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2><?php echo t('report_tickets_by_category'); ?></h2>
        <table class="data-table">
            <thead><tr><th><?php echo t('category'); ?></th><th><?php echo t('count'); ?></th></tr></thead>
            <tbody>
                <?php if ($by_category->num_rows === 0): ?>
                    <tr><td colspan="2"><?php echo t('no_ticket_data'); ?></td></tr>
                <?php else: ?>
                    <?php while ($row = $by_category->fetch_assoc()): ?>
                        <tr><td><?php echo htmlspecialchars(category_label($row['category'])); ?></td><td><?php echo $row['c']; ?></td></tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2><?php echo t('report_tickets_by_technician'); ?></h2>
        <table class="data-table">
            <thead><tr><th><?php echo t('nav_users'); ?></th><th><?php echo t('count'); ?></th></tr></thead>
            <tbody>
                <?php if ($by_technician->num_rows === 0): ?>
                    <tr><td colspan="2"><?php echo t('no_ticket_data'); ?></td></tr>
                <?php else: ?>
                    <?php while ($row = $by_technician->fetch_assoc()): ?>
                        <tr><td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo (int) $row['c']; ?></td></tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2><?php echo t('report_satisfaction'); ?></h2>
    <p style="font-size:13px; color:#1E293B;">
        <?php echo t('satisfied'); ?>: <strong><?php echo $sat_satisfied; ?></strong> &nbsp;|&nbsp;
        <?php echo t('unsatisfied'); ?>: <strong><?php echo $sat_unsatisfied; ?></strong> &nbsp;|&nbsp;
        <?php echo t('avg_rating'); ?>: <strong><?php echo $avg_rating_row['avg_r'] !== null ? round($avg_rating_row['avg_r'], 1) . '/5' : '—'; ?></strong>
    </p>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2><?php echo t('report_maintenance'); ?></h2>
    <p style="font-size:12px; color:#94A3B8; margin-bottom:10px;"><?php echo t('report_maintenance_note'); ?></p>
    <p style="font-size:13px; color:#1E293B;">
        <?php echo t('maintenance_overdue'); ?>: <strong><?php echo $maintenance_overdue; ?></strong> &nbsp;|&nbsp;
        <?php echo t('maintenance_upcoming'); ?>: <strong><?php echo $maintenance_upcoming; ?></strong>
    </p>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2>⚠ <?php echo t('report_predictive_flag'); ?> <span style="font-size:11px; color:#64748B; font-weight:normal;">(<?php echo t('report_predictive_flag_note'); ?>)</span></h2>
    <p style="font-size:13px; color:#64748B; margin-bottom:14px;"><?php echo t('report_predictive_flag_desc'); ?></p>
    <table class="data-table">
        <thead><tr><th><?php echo t('asset'); ?></th><th><?php echo t('report_tickets_90d'); ?></th></tr></thead>
        <tbody>
            <?php if (empty($at_risk)): ?>
                <tr><td colspan="2"><?php echo t('report_no_flagged'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($at_risk as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['name']); ?></td>
                        <td><span class="status-pill status-offline"><?php echo $a['ticket_count']; ?> <?php echo t('report_tickets_word'); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2><?php echo t('report_fault_history'); ?></h2>
    <p style="font-size:13px; color:#64748B; margin-bottom:14px;"><?php echo t('report_fault_history_desc'); ?></p>
    <?php if ($resolution_history->num_rows === 0): ?>
        <p style="font-size:13px; color:#64748B;"><?php echo t('report_no_resolutions'); ?></p>
    <?php else: ?>
        <?php while ($r = $resolution_history->fetch_assoc()): ?>
            <div style="padding:12px 0; border-bottom:1px solid #E2E8F0;">
                <p style="font-size:12px; color:#64748B; margin-bottom:4px;">
                    <strong><?php echo htmlspecialchars($r['asset_name']); ?></strong>
                    — <?php echo htmlspecialchars($r['reference_no'] ?? ''); ?>
                    — <?php echo date('d/m/Y', strtotime($r['created_at'])); ?>
                    <?php if ($r['satisfaction']): ?>
                        <?php echo $r['satisfaction'] === 'satisfied' ? '🙂' : '🙁'; ?>
                    <?php endif; ?>
                </p>
                <p style="font-size:13px; color:#1E293B;"><?php echo htmlspecialchars($r['resolution_comment']); ?></p>
                <?php if ($r['satisfaction_comment']): ?>
                    <p style="font-size:12px; color:#64748B; margin-top:4px; font-style:italic;">"<?php echo htmlspecialchars($r['satisfaction_comment']); ?>"</p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
