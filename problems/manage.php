<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['admin']); // Only Admin creates/manages Problems

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$problem = null;
$message = '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM problems WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $problem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$problem) {
        die('Problem not found.');
    }
}

// --- Create or Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_problem'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $root_cause = trim($_POST['root_cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $impact = $_POST['impact'] ?? 'medium';
    $status = $_POST['status'] ?? 'open';
    $assigned_to = (int) ($_POST['assigned_to'] ?? 0) ?: null;
    $resolution_date = trim($_POST['resolution_date'] ?? '') ?: null;
    $linked_ticket_ids = $_POST['ticket_ids'] ?? [];

    if ($title === '' || $description === '') {
        $message = t('fill_all_fields');
    } elseif ($id > 0) {
        $stmt = $conn->prepare("UPDATE problems SET title=?, description=?, root_cause=?, solution=?, impact=?, status=?, assigned_to=?, resolution_date=? WHERE id=?");
        $stmt->bind_param('ssssssisi', $title, $description, $root_cause, $solution, $impact, $status, $assigned_to, $resolution_date, $id);
        $stmt->execute();
        $stmt->close();
        $problem_id = $id;
    } else {
        $stmt = $conn->prepare("INSERT INTO problems (title, description, root_cause, solution, impact, status, assigned_to, resolution_date, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssisi', $title, $description, $root_cause, $solution, $impact, $status, $assigned_to, $resolution_date, $_SESSION['user_id']);
        $stmt->execute();
        $problem_id = $stmt->insert_id;
        $stmt->close();

        // Assign a reference number now that we have an ID (P001, P002...)
        $ref = generate_problem_reference($problem_id);
        $stmt = $conn->prepare("UPDATE problems SET reference_no = ? WHERE id = ?");
        $stmt->bind_param('si', $ref, $problem_id);
        $stmt->execute();
        $stmt->close();
    }

    // --- Sync linked tickets: unlink any previously linked ticket not
    //     in the new selection, then link the selected ones ---
    $conn->query("UPDATE tickets SET problem_id = NULL WHERE problem_id = " . (int) $problem_id);
    if (!empty($linked_ticket_ids)) {
        $ids = array_map('intval', $linked_ticket_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids) + 1);
        $stmt = $conn->prepare("UPDATE tickets SET problem_id = ? WHERE id IN ($placeholders)");
        $stmt->bind_param($types, $problem_id, ...$ids);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: view.php?id=' . $problem_id . '&saved=1');
    exit;
}

$technicians = $conn->query("SELECT id, name FROM users WHERE role = 'technician' ORDER BY name");

// All tickets, so the admin can pick which belong to this problem
// (shows current linkage, whether tied to this problem or unlinked/other)
$all_tickets = $conn->query("
    SELECT t.id, t.reference_no, t.title, a.name AS asset_name, t.problem_id
    FROM tickets t JOIN assets a ON a.id = t.asset_id
    ORDER BY t.created_at DESC
    LIMIT 100
");
$linked_now = [];
if ($id > 0) {
    $r = $conn->query("SELECT id FROM tickets WHERE problem_id = " . (int) $id);
    while ($row = $r->fetch_assoc()) { $linked_now[] = (int) $row['id']; }
}

$page_title = $problem ? t('edit_problem') : t('new_problem');
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?><div class="error-msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="panel" style="max-width:720px;">
    <form method="POST">
        <input type="hidden" name="save_problem" value="1">
        <?php if ($problem): ?><input type="hidden" name="id" value="<?php echo $problem['id']; ?>"><?php endif; ?>

        <div class="form-group">
            <label><?php echo t('ticket_title'); ?></label>
            <input type="text" name="title" required maxlength="200" value="<?php echo htmlspecialchars($problem['title'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
        </div>
        <div class="form-group">
            <label><?php echo t('description'); ?></label>
            <textarea name="description" rows="3" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"><?php echo htmlspecialchars($problem['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label><?php echo t('root_cause'); ?></label>
            <textarea name="root_cause" rows="2" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"><?php echo htmlspecialchars($problem['root_cause'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label><?php echo t('solution'); ?></label>
            <textarea name="solution" rows="2" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"><?php echo htmlspecialchars($problem['solution'] ?? ''); ?></textarea>
        </div>

        <div style="display:flex; gap:14px;">
            <div class="form-group" style="flex:1;">
                <label><?php echo t('impact'); ?></label>
                <select name="impact" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                    <?php foreach (['low','medium','high'] as $lvl): ?>
                        <option value="<?php echo $lvl; ?>" <?php echo ($problem['impact'] ?? 'medium') === $lvl ? 'selected' : ''; ?>><?php echo t('impact_' . $lvl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <label><?php echo t('status'); ?></label>
                <select name="status" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                    <?php foreach (['open','investigating','resolved','closed'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo ($problem['status'] ?? 'open') === $st ? 'selected' : ''; ?>><?php echo t('problem_status_' . $st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display:flex; gap:14px;">
            <div class="form-group" style="flex:1;">
                <label><?php echo t('assigned_technician'); ?></label>
                <select name="assigned_to" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                    <option value="">—</option>
                    <?php while ($t = $technicians->fetch_assoc()): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo (int)($problem['assigned_to'] ?? 0) === (int)$t['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;">
                <label><?php echo t('resolution_date'); ?></label>
                <input type="date" name="resolution_date" value="<?php echo htmlspecialchars($problem['resolution_date'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
            </div>
        </div>

        <div class="form-group">
            <label><?php echo t('related_tickets'); ?></label>
            <div style="max-height:220px; overflow-y:auto; border:1px solid #E2E8F0; border-radius:5px; padding:10px;">
                <?php if ($all_tickets->num_rows === 0): ?>
                    <p style="font-size:13px; color:#64748B;">—</p>
                <?php else: ?>
                    <?php while ($t = $all_tickets->fetch_assoc()): ?>
                        <label style="display:flex; align-items:center; gap:8px; padding:5px 0; font-size:13px;">
                            <input type="checkbox" name="ticket_ids[]" value="<?php echo $t['id']; ?>" style="width:auto;"
                                <?php echo in_array($t['id'], $linked_now, true) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($t['reference_no'] ?? ('#'.$t['id'])); ?> —
                            <?php echo htmlspecialchars($t['title'] ?: $t['asset_name']); ?>
                            <?php if ($t['problem_id'] && (int)$t['problem_id'] !== $id): ?>
                                <span style="color:#94A3B8;">(<?php echo t('linked_to_other_problem'); ?>)</span>
                            <?php endif; ?>
                        </label>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="btn-primary" style="width:auto; padding:11px 26px;"><?php echo t('save'); ?></button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
