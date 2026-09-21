<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_login();

$ticket_id = (int) ($_GET['id'] ?? $_POST['ticket_id'] ?? 0);

$stmt = $conn->prepare("SELECT t.*, a.name AS asset_name FROM tickets t JOIN assets a ON a.id = t.asset_id WHERE t.id = ?");
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found.');
}

// Only the person who reported it can rate it
if ((int) $ticket['reported_by'] !== (int) current_user_id()) {
    http_response_code(403);
    die('Only the person who reported this ticket can rate it.');
}

// Can only rate once the ticket is resolved/closed
if (!in_array($ticket['status'], ['resolved', 'closed'], true)) {
    die('This ticket is not resolved yet — feedback isn\'t available until it is.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $satisfaction = $_POST['satisfaction'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if (in_array($satisfaction, ['satisfied', 'unsatisfied'], true)) {
        $stmt = $conn->prepare("UPDATE tickets SET satisfaction = ?, satisfaction_comment = ? WHERE id = ?");
        $stmt->bind_param('ssi', $satisfaction, $comment, $ticket_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, change_note) VALUES (?, ?, ?)");
        $note = "User feedback: $satisfaction";
        $stmt->bind_param('iis', $ticket_id, $_SESSION['user_id'], $note);
        $stmt->execute();
        $stmt->close();

        $message = t('feedback_received');
        $ticket['satisfaction'] = $satisfaction;
        $ticket['satisfaction_comment'] = $comment;
    }
}

$page_title = t('rate_this_fix');
include __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="max-width:520px;">
    <h2>Ticket #<?php echo $ticket['id']; ?> — <?php echo htmlspecialchars($ticket['asset_name']); ?></h2>
    <p style="margin:10px 0 20px 0; color:#64748B; font-size:13px;"><?php echo htmlspecialchars($ticket['description']); ?></p>

    <?php if ($ticket['satisfaction']): ?>
        <p style="color:#16A34A; font-weight:600; margin-bottom:10px;">
            <?php echo t('feedback_received'); ?>
            (<?php echo $ticket['satisfaction'] === 'satisfied' ? t('satisfied') : t('unsatisfied'); ?>)
        </p>
        <?php if ($ticket['satisfaction_comment']): ?>
            <p style="font-size:13px; color:#1E293B; background:#F8FAFC; padding:12px; border-radius:6px;">
                "<?php echo htmlspecialchars($ticket['satisfaction_comment']); ?>"
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="font-weight:600; margin-bottom:14px;"><?php echo t('satisfaction_question'); ?></p>
        <form method="POST">
            <input type="hidden" name="submit_feedback" value="1">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">

            <div style="display:flex; gap:12px; margin-bottom:18px;">
                <label style="flex:1; text-align:center; padding:16px; border:2px solid #16A34A; border-radius:8px; cursor:pointer;">
                    <input type="radio" name="satisfaction" value="satisfied" required style="margin-bottom:8px;">
                    <div style="font-size:24px;">🙂</div>
                    <div style="font-size:13px; color:#16A34A; font-weight:600;"><?php echo t('satisfied'); ?></div>
                </label>
                <label style="flex:1; text-align:center; padding:16px; border:2px solid #DC2626; border-radius:8px; cursor:pointer;">
                    <input type="radio" name="satisfaction" value="unsatisfied" required style="margin-bottom:8px;">
                    <div style="font-size:24px;">🙁</div>
                    <div style="font-size:13px; color:#DC2626; font-weight:600;"><?php echo t('unsatisfied'); ?></div>
                </label>
            </div>

            <div class="form-group">
                <label><?php echo t('satisfaction_comment_label'); ?></label>
                <textarea name="comment" rows="3" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"></textarea>
            </div>

            <button type="submit" class="btn-primary" style="width:auto; padding:11px 26px;"><?php echo t('submit_feedback'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
