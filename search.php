<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/smart_rules.php';
require_login();

$q = trim($_GET['q'] ?? '');
$role = current_role();
$like = '%' . $q . '%';

$ticket_results = [];
$asset_results = [];
$kb_results = [];
$user_results = [];
$maintenance_results = [];

if ($q !== '') {
    // --- Tickets: scoped by role, same visibility rule as tickets/list.php ---
    if ($role === 'user') {
        $stmt = $conn->prepare("
            SELECT t.id, t.reference_no, t.title, t.status, a.name AS asset_name
            FROM tickets t JOIN assets a ON a.id = t.asset_id
            WHERE t.reported_by = ? AND (t.title LIKE ? OR t.description LIKE ? OR t.reference_no LIKE ?)
            ORDER BY t.created_at DESC LIMIT 20
        ");
        $stmt->bind_param('isss', $_SESSION['user_id'], $like, $like, $like);
    } elseif ($role === 'technician') {
        $stmt = $conn->prepare("
            SELECT t.id, t.reference_no, t.title, t.status, a.name AS asset_name
            FROM tickets t JOIN assets a ON a.id = t.asset_id
            WHERE t.assigned_to = ? AND (t.title LIKE ? OR t.description LIKE ? OR t.reference_no LIKE ?)
            ORDER BY t.created_at DESC LIMIT 20
        ");
        $stmt->bind_param('isss', $_SESSION['user_id'], $like, $like, $like);
    } else { // admin
        $stmt = $conn->prepare("
            SELECT t.id, t.reference_no, t.title, t.status, a.name AS asset_name
            FROM tickets t JOIN assets a ON a.id = t.asset_id
            WHERE t.title LIKE ? OR t.description LIKE ? OR t.reference_no LIKE ?
            ORDER BY t.created_at DESC LIMIT 20
        ");
        $stmt->bind_param('sss', $like, $like, $like);
    }
    $stmt->execute();
    $ticket_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- Assets: visible to everyone ---
    $stmt = $conn->prepare("SELECT id, name, type, location, status FROM assets WHERE name LIKE ? OR type LIKE ? OR location LIKE ? LIMIT 20");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $asset_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- Knowledge Base: visible to everyone ---
    $stmt = $conn->prepare("SELECT id, title, category FROM kb_articles WHERE title LIKE ? OR content LIKE ? LIMIT 20");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $kb_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- Maintenance records: Admin/Technician only ---
    if (in_array($role, ['admin', 'technician'], true)) {
        $stmt = $conn->prepare("
            SELECT m.id, a.name AS asset_name, m.next_due_date
            FROM maintenance_schedule m JOIN assets a ON a.id = m.asset_id
            WHERE a.name LIKE ? LIMIT 20
        ");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $maintenance_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // --- Users: Admin only ---
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 20");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $user_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$total_results = count($ticket_results) + count($asset_results) + count($kb_results) + count($maintenance_results) + count($user_results);

$page_title = t('search_results');
include __DIR__ . '/includes/header.php';
?>

<form method="GET" style="margin-bottom:20px; max-width:500px;">
    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="<?php echo t('global_search_placeholder'); ?>" autofocus
           style="width:100%; padding:12px; border:1px solid #E2E8F0; border-radius:5px; font-size:14px;">
</form>

<?php if ($q === ''): ?>
    <p style="font-size:13px; color:#64748B;"><?php echo t('type_to_search'); ?></p>
<?php elseif ($total_results === 0): ?>
    <p style="font-size:13px; color:#64748B;"><?php echo t('no_results_for'); ?> "<?php echo htmlspecialchars($q); ?>"</p>
<?php else: ?>

    <?php if (!empty($ticket_results)): ?>
    <div class="panel" style="margin-bottom:16px;">
        <h2><?php echo t('nav_tickets'); ?></h2>
        <?php foreach ($ticket_results as $t): ?>
            <p style="padding:8px 0; border-bottom:1px solid #E2E8F0; font-size:13px;">
                <a href="/sncmms/tickets/<?php echo $role === 'user' ? 'view' : 'update'; ?>.php?id=<?php echo $t['id']; ?>" style="color:#2563EB;">
                    <?php echo htmlspecialchars($t['reference_no'] ?? ('#'.$t['id'])); ?> — <?php echo htmlspecialchars($t['title'] ?: $t['asset_name']); ?>
                </a>
                <span class="status-pill status-<?php echo $t['status']; ?>" style="margin-left:8px;"><?php echo t('status_' . $t['status']); ?></span>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($asset_results)): ?>
    <div class="panel" style="margin-bottom:16px;">
        <h2><?php echo t('nav_assets'); ?></h2>
        <?php foreach ($asset_results as $a): ?>
            <p style="padding:8px 0; border-bottom:1px solid #E2E8F0; font-size:13px;">
                <?php echo htmlspecialchars($a['name']); ?> — <?php echo htmlspecialchars($a['type']); ?>
                <?php if ($a['location']): ?> (<?php echo htmlspecialchars($a['location']); ?>)<?php endif; ?>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($kb_results)): ?>
    <div class="panel" style="margin-bottom:16px;">
        <h2><?php echo t('nav_knowledge_base'); ?></h2>
        <?php foreach ($kb_results as $kb): ?>
            <p style="padding:8px 0; border-bottom:1px solid #E2E8F0; font-size:13px;">
                <a href="/sncmms/knowledge_base/view.php?id=<?php echo $kb['id']; ?>" style="color:#2563EB;"><?php echo htmlspecialchars($kb['title']); ?></a>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($maintenance_results)): ?>
    <div class="panel" style="margin-bottom:16px;">
        <h2><?php echo t('nav_maintenance'); ?></h2>
        <?php foreach ($maintenance_results as $m): ?>
            <p style="padding:8px 0; border-bottom:1px solid #E2E8F0; font-size:13px;">
                <?php echo htmlspecialchars($m['asset_name']); ?> — <?php echo t('next_due'); ?>: <?php echo date('d/m/Y', strtotime($m['next_due_date'])); ?>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($user_results)): ?>
    <div class="panel">
        <h2><?php echo t('nav_users'); ?></h2>
        <?php foreach ($user_results as $u): ?>
            <p style="padding:8px 0; border-bottom:1px solid #E2E8F0; font-size:13px;">
                <?php echo htmlspecialchars($u['name']); ?> — <?php echo htmlspecialchars($u['email']); ?>
                <span style="color:#94A3B8;">(<?php echo ucfirst($u['role']); ?>)</span>
            </p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
