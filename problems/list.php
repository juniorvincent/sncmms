<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['admin', 'technician']); // Users do not access Problem Management

$problems = $conn->query("
    SELECT p.*, u.name AS technician_name,
           (SELECT COUNT(*) FROM tickets t WHERE t.problem_id = p.id) AS ticket_count
    FROM problems p
    LEFT JOIN users u ON u.id = p.assigned_to
    ORDER BY p.created_at DESC
");

$page_title = t('nav_problems');
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <p style="font-size:13px; color:#64748B;"><?php echo t('problems_intro'); ?></p>
    <?php if (current_role() === 'admin'): ?>
        <a href="manage.php" class="btn-add">+ <?php echo t('new_problem'); ?></a>
    <?php endif; ?>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th><?php echo t('reference'); ?></th>
                <th><?php echo t('ticket_title'); ?></th>
                <th><?php echo t('impact'); ?></th>
                <th><?php echo t('status'); ?></th>
                <th><?php echo t('assigned_to'); ?></th>
                <th><?php echo t('related_tickets'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($problems->num_rows === 0): ?>
                <tr><td colspan="6"><?php echo t('no_problems_found'); ?></td></tr>
            <?php else: ?>
                <?php while ($p = $problems->fetch_assoc()): ?>
                    <tr>
                        <td><a href="view.php?id=<?php echo $p['id']; ?>" style="color:#2563EB;"><?php echo htmlspecialchars($p['reference_no']); ?></a></td>
                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                        <td><span class="priority-pill priority-<?php echo $p['impact'] === 'high' ? 'critical' : $p['impact']; ?>"><?php echo t('impact_' . $p['impact']); ?></span></td>
                        <td><span class="status-pill status-<?php echo $p['status'] === 'investigating' ? 'pending' : ($p['status'] === 'open' ? 'open' : $p['status']); ?>"><?php echo t('problem_status_' . $p['status']); ?></span></td>
                        <td><?php echo $p['technician_name'] ? htmlspecialchars($p['technician_name']) : '—'; ?></td>
                        <td><?php echo (int) $p['ticket_count']; ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
