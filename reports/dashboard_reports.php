<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login();

// --- Ticket counts by status (FR5.1) ---
$by_status = $conn->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status");

// --- Predictive maintenance flag (Week 5 PoC — see includes/smart_rules.php) ---
$at_risk = get_at_risk_assets($conn, 90, 3);

// --- Resolution history: technician fault descriptions + user satisfaction,
//     kept together per asset so patterns can inform future fault prevention ---
$resolution_history = $conn->query("
    SELECT t.reference_no, t.resolution_notes, t.satisfaction, t.satisfaction_comment,
           t.updated_at, a.name AS asset_name
    FROM tickets t JOIN assets a ON a.id = t.asset_id
    WHERE t.resolution_notes IS NOT NULL AND t.resolution_notes != ''
    ORDER BY t.updated_at DESC
    LIMIT 15
");

$page_title = t('nav_reports');
include __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="margin-bottom:20px;">
    <h2><?php echo t('report_tickets_by_status'); ?></h2>
    <table class="data-table">
        <thead><tr><th><?php echo t('status'); ?></th><th><?php echo t('count'); ?></th></tr></thead>
        <tbody>
            <?php if ($by_status->num_rows === 0): ?>
                <tr><td colspan="2"><?php echo t('no_ticket_data'); ?></td></tr>
            <?php else: ?>
                <?php while ($row = $by_status->fetch_assoc()): ?>
                    <tr>
                        <td><span class="status-pill status-<?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$row['status'])); ?></span></td>
                        <td><?php echo $row['c']; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
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
                    — <?php echo date('d/m/Y', strtotime($r['updated_at'])); ?>
                    <?php if ($r['satisfaction']): ?>
                        <?php echo $r['satisfaction'] === 'satisfied' ? '🙂' : '🙁'; ?>
                    <?php endif; ?>
                </p>
                <p style="font-size:13px; color:#1E293B;"><?php echo htmlspecialchars($r['resolution_notes']); ?></p>
                <?php if ($r['satisfaction_comment']): ?>
                    <p style="font-size:12px; color:#64748B; margin-top:4px; font-style:italic;">"<?php echo htmlspecialchars($r['satisfaction_comment']); ?>"</p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
