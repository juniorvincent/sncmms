<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login();

$ticket_id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT t.*, a.name AS asset_name FROM tickets t JOIN assets a ON a.id = t.asset_id WHERE t.id = ?");
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    die('Ticket not found.');
}

// Users may only view their own ticket; Admin/Technician can view any
// (e.g. following a link), but this page is really built for Users —
// technicians/admins use update.php to manage.
$is_owner = (int) $ticket['reported_by'] === (int) current_user_id();
if (current_role() === 'user' && !$is_owner) {
    http_response_code(403);
    die('You can only view your own tickets.');
}

$message = '';

// --- Satisfaction feedback (owner only, once resolved/closed) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!$is_owner) {
        http_response_code(403);
        die('Only the person who reported this ticket can rate it.');
    }
    $satisfaction = $_POST['satisfaction'] ?? '';
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (in_array($satisfaction, ['satisfied', 'unsatisfied'], true) && in_array($ticket['status'], ['resolved', 'closed'], true)) {
        $rating = max(1, min(5, $rating ?: ($satisfaction === 'satisfied' ? 5 : 2))); // sane default if no stars clicked
        $stmt = $conn->prepare("UPDATE tickets SET satisfaction = ?, satisfaction_rating = ?, satisfaction_comment = ? WHERE id = ?");
        $stmt->bind_param('sisi', $satisfaction, $rating, $comment, $ticket_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, change_note) VALUES (?, ?, ?)");
        $note = "User feedback: $satisfaction ($rating/5)";
        $stmt->bind_param('iis', $ticket_id, $_SESSION['user_id'], $note);
        $stmt->execute();
        $stmt->close();

        $message = t('feedback_received');
        $ticket['satisfaction'] = $satisfaction;
        $ticket['satisfaction_rating'] = $rating;
        $ticket['satisfaction_comment'] = $comment;
    }
}

// Comments visible to the User: technician/admin notes, per requirement
// "the User ... must be able to view the resolution comment and the
// complete ticket history" — showing the full thread, not just the
// resolution one, is the more transparent reading of that.
$comments = get_ticket_comments($conn, $ticket_id);

$stmt = $conn->prepare("
    SELECT h.change_note, h.changed_at, u.name
    FROM ticket_history h JOIN users u ON u.id = h.changed_by
    WHERE h.ticket_id = ? ORDER BY h.changed_at ASC
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

<div class="panel" style="max-width:640px; margin-bottom:20px;">
    <h2><?php echo htmlspecialchars($ticket['title'] ?: t('ticket') . ' #' . $ticket['id']); ?></h2>
    <p style="font-size:12px; color:#64748B; margin-bottom:10px;">
        <?php echo htmlspecialchars($ticket['reference_no'] ?? ''); ?> — <?php echo htmlspecialchars($ticket['asset_name']); ?>
        <?php if (!empty($ticket['location'])): ?> — 📍 <?php echo htmlspecialchars($ticket['location']); ?><?php endif; ?>
    </p>
    <p style="margin:10px 0; color:#1E293B;"><?php echo htmlspecialchars($ticket['description']); ?></p>
    <p style="margin-bottom:16px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
        <span><?php echo t('category'); ?>: <?php echo htmlspecialchars(category_label($ticket['category'] ?? 'other')); ?></span>
        <span class="priority-pill priority-<?php echo $ticket['priority']; ?>"><?php echo t('priority_' . $ticket['priority']); ?></span>
        <span class="status-pill status-<?php echo $ticket['status']; ?>"><?php echo t('status_' . $ticket['status']); ?></span>
        <?php $sla = sla_status($ticket['sla_due_at'] ?? null, $ticket['status']);
        if ($sla): ?>
            <?php if ($sla['breached']): ?>
                <span class="status-pill status-offline">🔴 <?php echo t('sla_breached'); ?></span>
            <?php else: ?>
                <span style="font-size:12px; color:#64748B;">⏱ <?php echo $sla['label']; ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </p>
</div>

<!-- Resolution comment(s) + full troubleshooting thread, read-only -->
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
</div>

<!-- Satisfaction feedback -->
<?php if ($is_owner && in_array($ticket['status'], ['resolved', 'closed'], true)): ?>
<div class="panel" style="max-width:640px; margin-bottom:20px;">
    <?php if ($ticket['satisfaction']): ?>
        <p style="color:#16A34A; font-weight:600; margin-bottom:6px;">
            <?php echo t('feedback_received'); ?>
            (<?php echo $ticket['satisfaction'] === 'satisfied' ? t('satisfied') : t('unsatisfied'); ?>)
        </p>
        <?php if (!empty($ticket['satisfaction_rating'])): ?>
            <p style="font-size:18px; margin-bottom:10px; letter-spacing:2px;">
                <?php for ($i = 1; $i <= 5; $i++): ?><?php echo $i <= $ticket['satisfaction_rating'] ? '⭐' : '☆'; ?><?php endfor; ?>
            </p>
        <?php endif; ?>
        <?php if ($ticket['satisfaction_comment']): ?>
            <p style="font-size:13px; color:#1E293B; background:#F8FAFC; padding:12px; border-radius:6px;">
                "<?php echo htmlspecialchars($ticket['satisfaction_comment']); ?>"
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="font-weight:600; margin-bottom:14px;"><?php echo t('satisfaction_question'); ?></p>
        <form method="POST">
            <input type="hidden" name="submit_feedback" value="1">
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
                <label><?php echo t('star_rating'); ?></label>
                <input type="hidden" name="rating" id="ratingInput" value="0">
                <div id="starPicker" style="font-size:28px; letter-spacing:4px; cursor:pointer;">
                    <span data-star="1">☆</span><span data-star="2">☆</span><span data-star="3">☆</span><span data-star="4">☆</span><span data-star="5">☆</span>
                </div>
                <script>
                (function() {
                    var stars = document.querySelectorAll('#starPicker span');
                    var input = document.getElementById('ratingInput');
                    stars.forEach(function(star) {
                        star.addEventListener('click', function() {
                            var val = parseInt(star.getAttribute('data-star'), 10);
                            input.value = val;
                            stars.forEach(function(s) {
                                s.textContent = parseInt(s.getAttribute('data-star'), 10) <= val ? '⭐' : '☆';
                            });
                        });
                    });
                })();
                </script>
            </div>
            <div class="form-group">
                <label><?php echo t('satisfaction_comment_label'); ?></label>
                <textarea name="comment" rows="3" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"></textarea>
            </div>
            <button type="submit" class="btn-primary" style="width:auto; padding:11px 26px;"><?php echo t('submit_feedback'); ?></button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Full ticket history -->
<div class="panel" style="max-width:640px;">
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
