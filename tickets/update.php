<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['technician', 'admin']); // only Technician/Admin manage tickets

$ticket_id = (int) ($_GET['id'] ?? $_POST['ticket_id'] ?? 0);

$stmt = $conn->prepare("SELECT t.*, a.name AS asset_name FROM tickets t JOIN assets a ON a.id = t.asset_id WHERE t.id = ?");
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found.');
}

// A technician may only manage a ticket assigned to them; Admin can manage any
if (current_role() === 'technician' && (int) $ticket['assigned_to'] !== (int) current_user_id()) {
    http_response_code(403);
    die('This ticket is not assigned to you.');
}

$message = '';
$error = '';

// --- Change status (Pending / In Progress / Resolved / Closed) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $allowed = ['pending', 'in_progress', 'resolved', 'closed'];

    // Resolving/closing requires a resolution comment on record — either
    // one already exists, or the technician provides one right now via
    // the comment box below (same submit).
    $requires_resolution = in_array($new_status, ['resolved', 'closed'], true);
    $already_has_resolution = has_resolution_comment($conn, $ticket_id);
    $comment_text = trim($_POST['comment'] ?? '');
    $mark_as_resolution = isset($_POST['mark_as_resolution']);

    if (!in_array($new_status, $allowed, true)) {
        $error = t('invalid_status');
    } elseif ($requires_resolution && !$already_has_resolution && ($comment_text === '' || !$mark_as_resolution)) {
        $error = t('resolution_notes_required');
    } else {
        $stmt = $conn->prepare("UPDATE tickets SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $ticket_id);
        $stmt->execute();
        $stmt->close();

        $note = "Status changed to $new_status";
        $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, change_note) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $ticket_id, $_SESSION['user_id'], $note);
        $stmt->execute();
        $stmt->close();

        if ($comment_text !== '') {
            add_ticket_comment($conn, $ticket_id, (int) current_user_id(), $comment_text, $mark_as_resolution);
        }

        $message = t('status_updated');
        $ticket['status'] = $new_status;
    }
}

// --- Add a comment without changing status (troubleshooting note) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment_only'])) {
    $comment_text = trim($_POST['comment'] ?? '');
    if ($comment_text !== '') {
        add_ticket_comment($conn, $ticket_id, (int) current_user_id(), $comment_text, false);
        $message = t('comment_added');
    }
}

$comments = get_ticket_comments($conn, $ticket_id);

// --- History log ---
$stmt = $conn->prepare("
    SELECT h.change_note, h.changed_at, u.name
    FROM ticket_history h JOIN users u ON u.id = h.changed_by
    WHERE h.ticket_id = ? ORDER BY h.changed_at DESC
");
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$history = $stmt->get_result();

$page_title = t('ticket') . ' #' . $ticket_id;
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="panel" style="max-width:640px; margin-bottom:20px;">
    <h2><?php echo htmlspecialchars($ticket['title'] ?: t('ticket') . ' #' . $ticket['id']); ?></h2>
    <p style="font-size:12px; color:#64748B; margin-bottom:10px;">
        <?php echo htmlspecialchars($ticket['reference_no'] ?? ''); ?> — <?php echo htmlspecialchars($ticket['asset_name']); ?>
        <?php if (!empty($ticket['location'])): ?> — 📍 <?php echo htmlspecialchars($ticket['location']); ?><?php endif; ?>
    </p>
    <p style="margin:10px 0; color:#1E293B;"><?php echo htmlspecialchars($ticket['description']); ?></p>
    <p style="margin-bottom:16px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
        <span class="priority-pill priority-<?php echo $ticket['priority']; ?>"><?php echo t('priority_' . $ticket['priority']); ?></span>
        <span><?php echo t('impact'); ?>: <?php echo t('impact_' . $ticket['impact']); ?></span>
        <span class="status-pill status-<?php echo $ticket['status']; ?>"><?php echo t('status_' . $ticket['status']); ?></span>
        <?php $sla = sla_status($ticket['sla_due_at'] ?? null, $ticket['status']);
        if ($sla): ?>
            <?php if ($sla['breached']): ?>
                <span class="status-pill status-offline">🔴 <?php echo t('sla_breached'); ?></span>
            <?php else: ?>
                <span style="font-size:12px; color:#64748B;">⏱ <?php echo t('sla'); ?>: <?php echo $sla['label']; ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if ($ticket['satisfaction']): ?>
        <div style="background:<?php echo $ticket['satisfaction']==='satisfied' ? '#F0FDF4' : '#FEF2F2'; ?>; border:1px solid <?php echo $ticket['satisfaction']==='satisfied' ? '#16A34A' : '#DC2626'; ?>; border-radius:6px; padding:12px; margin-bottom:18px;">
            <p style="font-size:13px; font-weight:600; color:<?php echo $ticket['satisfaction']==='satisfied' ? '#16A34A' : '#DC2626'; ?>;">
                <?php echo $ticket['satisfaction']==='satisfied' ? '🙂 '.t('satisfied') : '🙁 '.t('unsatisfied'); ?>
            </p>
            <?php if ($ticket['satisfaction_comment']): ?>
                <p style="font-size:13px; color:#1E293B; margin-top:6px;">"<?php echo htmlspecialchars($ticket['satisfaction_comment']); ?>"</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Status change + optional resolution comment in one action -->
    <form method="POST" id="statusForm">
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
        <div class="form-group">
            <label><?php echo t('update_status'); ?></label>
            <select name="status" id="statusSelect" onchange="document.getElementById('resNotice').style.display = (this.value==='resolved'||this.value==='closed') ? 'block' : 'none';" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value="pending" <?php echo $ticket['status']==='pending'?'selected':''; ?>><?php echo t('status_pending'); ?></option>
                <option value="in_progress" <?php echo $ticket['status']==='in_progress'?'selected':''; ?>><?php echo t('status_in_progress'); ?></option>
                <option value="resolved" <?php echo $ticket['status']==='resolved'?'selected':''; ?>><?php echo t('status_resolved'); ?></option>
                <option value="closed" <?php echo $ticket['status']==='closed'?'selected':''; ?>><?php echo t('status_closed'); ?></option>
            </select>
        </div>

        <div id="resNotice" style="display:<?php echo in_array($ticket['status'], ['resolved','closed'], true) ? 'block' : 'none'; ?>; background:#FFFBEB; border:1px solid #F59E0B; border-radius:6px; padding:10px 12px; margin-bottom:14px; font-size:12px; color:#92400E;">
            <?php echo has_resolution_comment($conn, $ticket_id) ? t('resolution_already_recorded') : t('resolution_required_hint'); ?>
        </div>

        <div class="form-group">
            <label><?php echo t('comment_label'); ?></label>
            <textarea name="comment" rows="3" placeholder="<?php echo t('resolution_notes_placeholder'); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"></textarea>
        </div>
        <div class="form-group" style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="mark_as_resolution" id="markRes" value="1" style="width:auto;">
            <label for="markRes" style="margin:0; font-size:13px;"><?php echo t('mark_as_resolution_comment'); ?></label>
        </div>

        <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;"><?php echo t('save'); ?></button>
    </form>
</div>

<!-- Comment thread -->
<div class="panel" style="max-width:640px; margin-bottom:20px;">
    <h2><?php echo t('comments'); ?></h2>
    <?php if (empty($comments)): ?>
        <p style="font-size:13px; color:#64748B;"><?php echo t('no_comments_yet'); ?></p>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div style="padding:12px 0; border-bottom:1px solid #E2E8F0;">
                <p style="font-size:12px; color:#64748B; margin-bottom:4px;">
                    <strong style="color:#1E293B;"><?php echo htmlspecialchars($c['name']); ?></strong>
                    (<?php echo ucfirst($c['role']); ?>) — <?php echo $c['created_at']; ?>
                    <?php if ($c['is_resolution']): ?>
                        <span class="status-pill status-resolved" style="margin-left:6px;"><?php echo t('resolution'); ?></span>
                    <?php endif; ?>
                </p>
                <p style="font-size:13px; color:#1E293B;"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="POST" style="margin-top:14px;">
        <input type="hidden" name="add_comment_only" value="1">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
        <textarea name="comment" rows="2" placeholder="<?php echo t('add_comment_placeholder'); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px; margin-bottom:8px;"></textarea>
        <button type="submit" class="btn-add" style="padding:8px 18px; font-size:13px;"><?php echo t('add_comment'); ?></button>
    </form>
</div>

<div class="panel">
    <h2><?php echo t('history'); ?></h2>
    <?php if ($history->num_rows === 0): ?>
        <p style="font-size:13px; color:#64748B;"><?php echo t('no_history'); ?></p>
    <?php else: ?>
        <?php while ($h = $history->fetch_assoc()): ?>
            <p style="font-size:13px; padding:8px 0; border-bottom:1px solid #E2E8F0;">
                <strong><?php echo htmlspecialchars($h['name']); ?></strong> — <?php echo htmlspecialchars($h['change_note']); ?>
                <span style="color:#64748B;"> (<?php echo $h['changed_at']; ?>)</span>
            </p>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
