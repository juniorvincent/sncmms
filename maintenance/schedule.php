<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_role(['admin']); // only Admin schedules maintenance

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int) $_POST['asset_id'];
    $frequency = (int) $_POST['frequency_months'];

    $next_due = date('Y-m-d', strtotime("+$frequency months"));

    $stmt = $conn->prepare("INSERT INTO maintenance_schedule (asset_id, frequency_months, next_due_date) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $asset_id, $frequency, $next_due);
    $stmt->execute();
    $stmt->close();

    $message = t('schedule_created') . ': ' . $next_due;
}

$assets_list = $conn->query("SELECT id, name FROM assets ORDER BY name");

$schedules = $conn->query("
    SELECT m.id, a.name AS asset_name, m.frequency_months, m.next_due_date,
           CASE WHEN m.next_due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
    FROM maintenance_schedule m
    JOIN assets a ON a.id = m.asset_id
    ORDER BY m.next_due_date ASC
");

$page_title = t('maintenance_schedule_title');
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="panel" style="max-width:600px; margin-bottom:20px;">
    <h2><?php echo t('create_schedule'); ?></h2>
    <form method="POST">
        <div class="form-group">
            <label><?php echo t('asset'); ?></label>
            <select name="asset_id" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value=""><?php echo t('choose_an_asset'); ?></option>
                <?php while ($a = $assets_list->fetch_assoc()): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('frequency_months'); ?></label>
            <input type="number" name="frequency_months" min="1" value="3" required>
        </div>
        <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;"><?php echo t('create_schedule_btn'); ?></button>
    </form>
</div>

<div class="panel">
    <h2><?php echo t('upcoming_overdue'); ?></h2>
    <table class="data-table">
        <thead><tr><th><?php echo t('asset'); ?></th><th><?php echo t('frequency_months'); ?></th><th><?php echo t('next_due'); ?></th><th><?php echo t('status'); ?></th></tr></thead>
        <tbody>
            <?php if ($schedules->num_rows === 0): ?>
                <tr><td colspan="4"><?php echo t('no_schedules_yet'); ?></td></tr>
            <?php else: ?>
                <?php while ($s = $schedules->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['asset_name']); ?></td>
                        <td><?php echo sprintf(t('every_x_months'), $s['frequency_months']); ?></td>
                        <td><?php echo $s['next_due_date']; ?></td>
                        <td>
                            <?php if ($s['is_overdue']): ?>
                                <span class="status-pill status-offline"><?php echo t('overdue'); ?></span>
                            <?php else: ?>
                                <span class="status-pill status-maintenance"><?php echo t('scheduled'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
