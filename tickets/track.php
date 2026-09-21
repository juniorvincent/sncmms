<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';

$ticket = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference_no = trim($_POST['reference_no'] ?? '');

    if ($reference_no === '') {
        $error = t('enter_reference');
    } else {
        // Public lookup by reference number only — deliberately returns
        // limited fields (no internal description, no assignee name)
        // to avoid exposing internal details to an unauthenticated visitor.
        $stmt = $conn->prepare("
            SELECT t.reference_no, t.category, t.priority, t.status, t.created_at, t.sla_due_at, a.name AS asset_name
            FROM tickets t JOIN assets a ON a.id = t.asset_id
            WHERE t.reference_no = ?
        ");
        $stmt->bind_param('s', $reference_no);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) {
            $error = t('ticket_not_found');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title>SNCMMS — <?php echo t('track_ticket_title'); ?></title>
    <link rel="stylesheet" href="/sncmms/assets/css/style.css">
</head>
<body class="login-page">

    <div class="login-header" style="display:flex; justify-content:space-between; align-items:center;">
        <a href="/sncmms/index.php" style="color:inherit; text-decoration:none;">SNCMMS — Smart Network &amp; Computer Maintenance</a>
        <div class="lang-switch" style="margin:0;">
            <a href="/sncmms/includes/set_language.php?lang=en" style="color:#fff;<?php echo $_SESSION['lang']==='en'?'font-weight:700;':'opacity:.6;'; ?>">EN</a>
            <span style="color:#fff;">|</span>
            <a href="/sncmms/includes/set_language.php?lang=fr" style="color:#fff;<?php echo $_SESSION['lang']==='fr'?'font-weight:700;':'opacity:.6;'; ?>">FR</a>
        </div>
    </div>

    <div class="login-box" style="max-width:480px;">
        <h1><?php echo t('track_ticket_title'); ?></h1>
        <p style="font-size:13px; color:#64748B; margin-bottom:20px;">
            <?php echo t('track_ticket_desc'); ?>
        </p>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><?php echo t('reference_number'); ?></label>
                <input type="text" name="reference_no" placeholder="TK-2026-000123" value="<?php echo htmlspecialchars($_POST['reference_no'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="btn-primary"><?php echo t('track'); ?></button>
        </form>

        <?php if ($ticket): ?>
            <div style="margin-top:24px; padding-top:20px; border-top:1px solid #E2E8F0;">
                <p style="font-size:13px; color:#64748B;"><?php echo t('reference'); ?></p>
                <p style="font-weight:700; margin-bottom:14px;"><?php echo htmlspecialchars($ticket['reference_no']); ?></p>

                <p style="font-size:13px; color:#64748B;"><?php echo t('status'); ?></p>
                <p style="margin-bottom:14px;"><span class="status-pill status-<?php echo $ticket['status']; ?>"><?php echo t('status_' . $ticket['status']); ?></span></p>

                <p style="font-size:13px; color:#64748B;"><?php echo t('category'); ?></p>
                <p style="margin-bottom:14px;"><?php echo htmlspecialchars(category_label($ticket['category'])); ?></p>

                <p style="font-size:13px; color:#64748B;"><?php echo t('equipment'); ?></p>
                <p style="margin-bottom:14px;"><?php echo htmlspecialchars($ticket['asset_name']); ?></p>

                <p style="font-size:13px; color:#64748B;"><?php echo t('submitted_on'); ?></p>
                <p><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
            </div>
        <?php endif; ?>

        <p style="margin-top:20px; text-align:center; font-size:12px;">
            <a href="/sncmms/index.php" style="color:#2563EB;"><?php echo t('back_to_home'); ?></a>
        </p>
    </div>

</body>
</html>
