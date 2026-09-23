<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_login();

// Clicking a notification marks it read and follows its link
if (isset($_GET['open'])) {
    $nid = (int) $_GET['open'];
    $stmt = $conn->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $nid, $_SESSION['user_id']);
    $stmt->execute();
    $n = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($n) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $nid);
        $stmt->execute();
        $stmt->close();
        header('Location: ' . ($n['link'] ?: 'list.php'));
        exit;
    }
}

if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    header('Location: list.php');
    exit;
}

$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . (int) current_user_id() . " ORDER BY created_at DESC LIMIT 50");

$page_title = t('notifications');
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <span></span>
    <a href="?mark_all_read=1" style="font-size:13px; color:#2563EB;"><?php echo t('mark_all_read'); ?></a>
</div>

<div class="panel">
    <?php if ($notifications->num_rows === 0): ?>
        <p style="font-size:13px; color:#64748B;"><?php echo t('no_notifications'); ?></p>
    <?php else: ?>
        <?php while ($n = $notifications->fetch_assoc()): ?>
            <a href="?open=<?php echo $n['id']; ?>" style="display:block; padding:12px 0; border-bottom:1px solid #E2E8F0; text-decoration:none; <?php echo $n['is_read'] ? '' : 'background:#EFF6FF;'; ?>">
                <p style="font-size:13px; color:#1E293B; <?php echo $n['is_read'] ? '' : 'font-weight:600;'; ?>">
                    <?php echo $n['is_read'] ? '' : '🔵 '; ?><?php echo htmlspecialchars($n['message']); ?>
                </p>
                <p style="font-size:11px; color:#94A3B8; margin-top:3px;"><?php echo $n['created_at']; ?></p>
            </a>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
