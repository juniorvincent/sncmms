<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';

// Any logged-in role can view the dashboard (Admin, Technician, User)
require_login();

// Opportunistic SLA-breach check (no cron in this project — see smart_rules.php)
if (current_role() === 'admin') {
    check_and_notify_sla_breaches($conn);
}

// --- Summary counts ---
$total_assets   = $conn->query("SELECT COUNT(*) AS c FROM assets")->fetch_assoc()['c'];
$open_tickets   = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'open'")->fetch_assoc()['c'];
$critical_tickets = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE priority = 'critical' AND status NOT IN ('resolved','closed')")->fetch_assoc()['c'];
$pending_tickets  = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('pending','in_progress')")->fetch_assoc()['c'];
$resolved_30d   = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status IN ('resolved','closed') AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
$offline_assets = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status = 'faulty'")->fetch_assoc()['c'];
$online_assets  = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status = 'active'")->fetch_assoc()['c'];
$maintenance_assets = $conn->query("SELECT COUNT(*) AS c FROM assets WHERE status = 'retired'")->fetch_assoc()['c'];

// --- Chart data: Tickets by Status ---
$status_data = ['open' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status");
while ($row = $res->fetch_assoc()) { $status_data[$row['status']] = (int) $row['c']; }

// --- Chart data: Tickets by Priority ---
$priority_data = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
$res = $conn->query("SELECT priority, COUNT(*) AS c FROM tickets GROUP BY priority");
while ($row = $res->fetch_assoc()) { $priority_data[$row['priority']] = (int) $row['c']; }

// --- Chart data: Equipment Status ---
$equip_data = ['active' => $online_assets, 'faulty' => $offline_assets, 'retired' => $maintenance_assets];

// --- Chart data: User Satisfaction ---
$sat_satisfied = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE satisfaction = 'satisfied'")->fetch_assoc()['c'];
$sat_unsatisfied = $conn->query("SELECT COUNT(*) AS c FROM tickets WHERE satisfaction = 'unsatisfied'")->fetch_assoc()['c'];
$sat_total = $sat_satisfied + $sat_unsatisfied;
$sat_percent = $sat_total > 0 ? round($sat_satisfied / $sat_total * 100) : null;
$avg_rating_row = $conn->query("SELECT AVG(satisfaction_rating) AS avg_r FROM tickets WHERE satisfaction_rating IS NOT NULL")->fetch_assoc();
$avg_rating = $avg_rating_row['avg_r'] !== null ? round($avg_rating_row['avg_r'], 1) : null;

// --- Recent tickets table ---
$recent_tickets = $conn->query("
    SELECT t.id, t.title, t.priority, a.name AS asset_name, t.status, u.name AS assigned_name
    FROM tickets t
    JOIN assets a ON a.id = t.asset_id
    LEFT JOIN users u ON u.id = t.assigned_to
    ORDER BY t.created_at DESC
    LIMIT 10
");

$page_title = t('dashboard_title');
include __DIR__ . '/../includes/header.php';
?>

<h1 style="margin-bottom:16px;"><?php echo t('dashboard_title'); ?></h1>

<div class="cards" style="flex-wrap:wrap;">
    <div class="card total">
        <h3><?php echo t('card_total_assets'); ?></h3>
        <div class="value"><?php echo $total_assets; ?></div>
    </div>
    <div class="card open">
        <h3><?php echo t('card_open_tickets'); ?></h3>
        <div class="value"><?php echo $open_tickets; ?></div>
    </div>
    <div class="card offline">
        <h3><?php echo t('card_critical_tickets'); ?></h3>
        <div class="value"><?php echo $critical_tickets; ?></div>
    </div>
    <div class="card" style="background:#F59E0B;">
        <h3><?php echo t('card_pending_tickets'); ?></h3>
        <div class="value"><?php echo $pending_tickets; ?></div>
    </div>
    <div class="card resolved">
        <h3><?php echo t('card_resolved'); ?></h3>
        <div class="value"><?php echo $resolved_30d; ?></div>
    </div>
    <div class="card" style="background:#16A34A;">
        <h3><?php echo t('card_online_equipment'); ?></h3>
        <div class="value"><?php echo $online_assets; ?></div>
    </div>
    <div class="card" style="background:#F59E0B;">
        <h3><?php echo t('card_maintenance_equipment'); ?></h3>
        <div class="value"><?php echo $maintenance_assets; ?></div>
    </div>
    <div class="card offline">
        <h3><?php echo t('card_offline'); ?></h3>
        <div class="value"><?php echo $offline_assets; ?></div>
    </div>
</div>

<!-- Charts -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin:24px 0;">
    <div class="panel"><h2 style="font-size:14px; margin-bottom:10px;"><?php echo t('chart_tickets_by_status'); ?></h2><canvas id="chartStatus" height="200"></canvas></div>
    <div class="panel"><h2 style="font-size:14px; margin-bottom:10px;"><?php echo t('chart_tickets_by_priority'); ?></h2><canvas id="chartPriority" height="200"></canvas></div>
    <div class="panel"><h2 style="font-size:14px; margin-bottom:10px;"><?php echo t('chart_equipment_status'); ?></h2><canvas id="chartEquipment" height="200"></canvas></div>
    <div class="panel">
        <h2 style="font-size:14px; margin-bottom:10px;"><?php echo t('chart_satisfaction'); ?></h2>
        <?php if ($sat_percent === null): ?>
            <p style="font-size:13px; color:#64748B;"><?php echo t('no_feedback_yet'); ?></p>
        <?php else: ?>
            <canvas id="chartSatisfaction" height="150"></canvas>
            <p style="font-size:12px; color:#64748B; margin-top:8px;">
                <?php echo t('avg_rating'); ?>: <strong><?php echo $avg_rating ?? '—'; ?>/5</strong> &nbsp;|&nbsp;
                <?php echo t('satisfaction_rate'); ?>: <strong><?php echo $sat_percent; ?>%</strong>
            </p>
        <?php endif; ?>
    </div>
</div>

<h2 style="font-size:16px; margin-bottom:10px;"><?php echo t('recent_tickets'); ?></h2>
<div class="panel" style="overflow-x:auto;">
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th><?php echo t('ticket_title'); ?></th>
            <th><?php echo t('priority'); ?></th>
            <th><?php echo t('nav_assets'); ?></th>
            <th><?php echo t('status'); ?></th>
            <th><?php echo t('assigned_to'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($recent_tickets->num_rows === 0): ?>
            <tr><td colspan="6"><?php echo t('no_tickets_yet'); ?></td></tr>
        <?php else: ?>
            <?php while ($row = $recent_tickets->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['title'] ?: $row['asset_name']); ?></td>
                    <td><span class="priority-pill priority-<?php echo $row['priority']; ?>"><?php echo t('priority_' . $row['priority']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                    <td><span class="status-pill status-<?php echo $row['status']; ?>"><?php echo t('status_' . $row['status']); ?></span></td>
                    <td><?php echo $row['assigned_name'] ? htmlspecialchars($row['assigned_name']) : '—'; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const palette = { red: '#DC2626', orange: '#F59E0B', yellow: '#FDE68A', green: '#16A34A', blue: '#2563EB', gray: '#94A3B8' };

new Chart(document.getElementById('chartStatus'), {
    type: 'bar',
    data: {
        labels: ['<?php echo t('status_open'); ?>', '<?php echo t('status_pending'); ?>', '<?php echo t('status_in_progress'); ?>', '<?php echo t('status_resolved'); ?>', '<?php echo t('status_closed'); ?>'],
        datasets: [{
            data: [<?php echo $status_data['open']; ?>, <?php echo $status_data['pending']; ?>, <?php echo $status_data['in_progress']; ?>, <?php echo $status_data['resolved']; ?>, <?php echo $status_data['closed']; ?>],
            backgroundColor: [palette.red, palette.orange, palette.blue, palette.green, palette.gray]
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('chartPriority'), {
    type: 'doughnut',
    data: {
        labels: ['<?php echo t('priority_critical'); ?>', '<?php echo t('priority_high'); ?>', '<?php echo t('priority_medium'); ?>', '<?php echo t('priority_low'); ?>'],
        datasets: [{
            data: [<?php echo $priority_data['critical']; ?>, <?php echo $priority_data['high']; ?>, <?php echo $priority_data['medium']; ?>, <?php echo $priority_data['low']; ?>],
            backgroundColor: [palette.red, palette.orange, palette.yellow, palette.green]
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});

new Chart(document.getElementById('chartEquipment'), {
    type: 'doughnut',
    data: {
        labels: ['<?php echo t('asset_status_online'); ?>', '<?php echo t('asset_status_offline'); ?>', '<?php echo t('card_maintenance_equipment'); ?>'],
        datasets: [{
            data: [<?php echo $equip_data['active']; ?>, <?php echo $equip_data['faulty']; ?>, <?php echo $equip_data['retired']; ?>],
            backgroundColor: [palette.green, palette.red, palette.orange]
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});

<?php if ($sat_percent !== null): ?>
new Chart(document.getElementById('chartSatisfaction'), {
    type: 'doughnut',
    data: {
        labels: ['<?php echo t('satisfied'); ?>', '<?php echo t('unsatisfied'); ?>'],
        datasets: [{ data: [<?php echo $sat_satisfied; ?>, <?php echo $sat_unsatisfied; ?>], backgroundColor: [palette.green, palette.red] }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
