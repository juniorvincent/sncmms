<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login();

$role = current_role();
$message = '';

// --- Admin: (re)assign a ticket to a technician ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ticket'])) {
    if ($role !== 'admin') {
        http_response_code(403);
        die('Only Admin can assign tickets.');
    }
    $ticket_id = (int) $_POST['ticket_id'];
    $technician_id = (int) $_POST['technician_id'];

    $stmt = $conn->prepare("UPDATE tickets SET assigned_to = ?, status = 'pending' WHERE id = ?");
    $stmt->bind_param('ii', $technician_id, $ticket_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, change_note) VALUES (?, ?, 'Assigned to technician')");
    $stmt->bind_param('ii', $ticket_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT reference_no, reported_by FROM tickets WHERE id = ?");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $t_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $ref = $t_row['reference_no'] ?? ('#' . $ticket_id);

    notify_user($conn, $technician_id, "New ticket $ref has been assigned to you.", "/sncmms/tickets/update.php?id=$ticket_id");
    notify_user($conn, (int) $t_row['reported_by'], "Your ticket $ref has been assigned to a technician.", "/sncmms/tickets/view.php?id=$ticket_id");

    $message = t('ticket') . " #$ticket_id " . t('ticket_assigned');
}

// --- Filters (status, priority, category, technician) ---
$f_status = $_GET['f_status'] ?? '';
$f_priority = $_GET['f_priority'] ?? '';
$f_category = $_GET['f_category'] ?? '';
$f_technician = (int) ($_GET['f_technician'] ?? 0);

$filter_sql = '';
$filter_types = '';
$filter_params = [];
if ($f_status !== '') { $filter_sql .= ' AND t.status = ?'; $filter_types .= 's'; $filter_params[] = $f_status; }
if ($f_priority !== '') { $filter_sql .= ' AND t.priority = ?'; $filter_types .= 's'; $filter_params[] = $f_priority; }
if ($f_category !== '') { $filter_sql .= ' AND t.category = ?'; $filter_types .= 's'; $filter_params[] = $f_category; }
if ($f_technician > 0) { $filter_sql .= ' AND t.assigned_to = ?'; $filter_types .= 'i'; $filter_params[] = $f_technician; }

// --- Role-based ticket visibility ---
if ($role === 'user') {
    $sql = "
        SELECT t.*, a.name AS asset_name, u.name AS assigned_name
        FROM tickets t JOIN assets a ON a.id = t.asset_id
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE t.reported_by = ?" . $filter_sql . "
        ORDER BY t.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i' . $filter_types, $_SESSION['user_id'], ...$filter_params);
    $stmt->execute();
    $tickets = $stmt->get_result();
} elseif ($role === 'technician') {
    $sql = "
        SELECT t.*, a.name AS asset_name, u.name AS assigned_name
        FROM tickets t JOIN assets a ON a.id = t.asset_id
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE t.assigned_to = ?" . $filter_sql . "
        ORDER BY t.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i' . $filter_types, $_SESSION['user_id'], ...$filter_params);
    $stmt->execute();
    $tickets = $stmt->get_result();
} else { // admin sees all
    $sql = "
        SELECT t.*, a.name AS asset_name, u.name AS assigned_name
        FROM tickets t JOIN assets a ON a.id = t.asset_id
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE 1=1" . $filter_sql . "
        ORDER BY t.created_at DESC
    ";
    if ($filter_types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($filter_types, ...$filter_params);
        $stmt->execute();
        $tickets = $stmt->get_result();
    } else {
        $tickets = $conn->query($sql);
    }
}

$technicians = ($role === 'admin')
    ? $conn->query("SELECT id, name FROM users WHERE role = 'technician'")
    : null;

$page_title = ($role === 'user') ? t('my_tickets') : t('nav_tickets');
include __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['created'])): ?>
    <div class="panel" style="border-left:4px solid #16A34A; margin-bottom:16px;">
        <p style="color:#16A34A; font-weight:600;"><?php echo t('ticket_submitted'); ?></p>
        <p style="font-size:13px; color:#64748B; margin-top:6px;">
            <?php echo t('your_reference'); ?>: <strong style="color:#1E293B; font-size:15px;"><?php echo htmlspecialchars($_GET['ref'] ?? '#' . (int)$_GET['created']); ?></strong>
            — <?php echo t('keep_to_track'); ?>
        </p>
    </div>
<?php endif; ?>
<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div style="margin-bottom:16px;">
    <a href="create.php" class="btn-add"><?php echo t('new_ticket'); ?></a>
</div>

<form method="GET" class="panel no-print" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
    <div>
        <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;"><?php echo t('status'); ?></label>
        <select name="f_status" style="padding:8px; border:1px solid #E2E8F0; border-radius:5px; font-size:13px;">
            <option value=""><?php echo t('all_statuses'); ?></option>
            <?php foreach (['open','pending','in_progress','resolved','closed'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $f_status === $s ? 'selected' : ''; ?>><?php echo t('status_' . $s); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;"><?php echo t('priority'); ?></label>
        <select name="f_priority" style="padding:8px; border:1px solid #E2E8F0; border-radius:5px; font-size:13px;">
            <option value=""><?php echo t('all_priorities'); ?></option>
            <?php foreach (['critical','high','medium','low'] as $p): ?>
                <option value="<?php echo $p; ?>" <?php echo $f_priority === $p ? 'selected' : ''; ?>><?php echo t('priority_' . $p); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;"><?php echo t('category'); ?></label>
        <select name="f_category" style="padding:8px; border:1px solid #E2E8F0; border-radius:5px; font-size:13px;">
            <option value=""><?php echo t('all_categories'); ?></option>
            <?php foreach (ticket_categories() as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $f_category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars(category_label($cat)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($role === 'admin'): ?>
    <div>
        <label style="display:block; font-size:11px; color:#64748B; margin-bottom:4px;"><?php echo t('nav_users'); ?></label>
        <select name="f_technician" style="padding:8px; border:1px solid #E2E8F0; border-radius:5px; font-size:13px;">
            <option value="0"><?php echo t('all_technicians'); ?></option>
            <?php
            $tech_filter_list = $conn->query("SELECT id, name FROM users WHERE role = 'technician' ORDER BY name");
            while ($tf = $tech_filter_list->fetch_assoc()): ?>
                <option value="<?php echo $tf['id']; ?>" <?php echo $f_technician === (int) $tf['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tf['name']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn-add" style="padding:8px 16px; font-size:13px;"><?php echo t('filter'); ?></button>
    <?php if ($f_status || $f_priority || $f_category || $f_technician): ?>
        <a href="list.php" style="font-size:12px; color:#64748B; padding:8px 0;"><?php echo t('clear_filters'); ?></a>
    <?php endif; ?>
</form>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th><?php echo t('reference'); ?></th><th><?php echo t('asset'); ?></th><th><?php echo t('category'); ?></th>
                <th><?php echo t('description'); ?></th><th><?php echo t('priority'); ?></th><th><?php echo t('sla'); ?></th>
                <th><?php echo t('status'); ?></th><th><?php echo t('assigned'); ?></th>
                <?php if ($role === 'admin' || $role === 'technician'): ?><th><?php echo t('action'); ?></th><?php endif; ?>
                <?php if ($role === 'user'): ?><th><?php echo t('view'); ?></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($tickets->num_rows === 0): ?>
                <tr><td colspan="9"><?php echo t('no_tickets_yet'); ?></td></tr>
            <?php else: ?>
                <?php while ($row = $tickets->fetch_assoc()): ?>
                    <?php
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['reference_no'] ?? ('#'.$row['id'])); ?></td>
                        <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars(category_label($row['category'] ?? 'other')); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 40, '...')); ?></td>
                        <td><span class="priority-pill priority-<?php echo $row['priority']; ?>"><?php echo t('priority_' . $row['priority']); ?></span></td>
                        <td>
                            <?php $sla = sla_status($row['sla_due_at'] ?? null, $row['status']);
                            if ($sla === null): ?>
                                <span style="font-size:11px; color:#64748B;">—</span>
                            <?php elseif ($sla['breached']): ?>
                                <span class="status-pill status-offline">🔴 <?php echo t('sla_breached'); ?></span>
                            <?php else: ?>
                                <span style="font-size:11px; color:#64748B;"><?php echo $sla['label']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="status-pill status-<?php echo $row['status']; ?>"><?php echo t('status_' . $row['status']); ?></span></td>
                        <td><?php echo $row['assigned_name'] ? htmlspecialchars($row['assigned_name']) : '—'; ?></td>
                        <?php if ($role === 'admin'): ?>
                        <td>
                            <?php if (!$row['assigned_to']): ?>
                                <form method="POST" style="display:flex; gap:6px;">
                                    <input type="hidden" name="assign_ticket" value="1">
                                    <input type="hidden" name="ticket_id" value="<?php echo $row['id']; ?>">
                                    <select name="technician_id" required style="padding:4px; border:1px solid #E2E8F0; border-radius:4px; font-size:12px;">
                                        <option value=""><?php echo t('assign_to'); ?></option>
                                        <?php
                                        $technicians->data_seek(0);
                                        while ($t = $technicians->fetch_assoc()): ?>
                                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="submit" class="btn-add" style="padding:4px 10px; font-size:12px;"><?php echo t('go'); ?></button>
                                </form>
                            <?php else: ?>
                                <a href="update.php?id=<?php echo $row['id']; ?>" style="font-size:12px; color:#2563EB;"><?php echo t('manage'); ?></a>
                            <?php endif; ?>
                        </td>
                        <?php elseif ($role === 'technician'): ?>
                        <td>
                            <a href="update.php?id=<?php echo $row['id']; ?>" style="font-size:12px; color:#2563EB;"><?php echo t('manage'); ?></a>
                        </td>
                        <?php endif; ?>
                        <?php if ($role === 'user'): ?>
                        <td>
                            <a href="view.php?id=<?php echo $row['id']; ?>" style="font-size:12px; color:#2563EB;"><?php echo t('view'); ?></a>
                            <?php if ($row['satisfaction']): ?>
                                <span style="font-size:14px; margin-left:6px;"><?php echo $row['satisfaction'] === 'satisfied' ? '🙂' : '🙁'; ?></span>
                            <?php elseif (in_array($row['status'], ['resolved', 'closed'], true)): ?>
                                <span style="font-size:11px; color:#F59E0B; margin-left:4px;">●</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
