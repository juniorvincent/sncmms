<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_login(); // all roles can view

$message = '';

// --- Handle "Add Asset" form (Admin only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    if (current_role() !== 'admin') {
        http_response_code(403);
        die('Only Admin can add assets.');
    }
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $serial = trim($_POST['serial_number'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name === '' || $type === '') {
        $message = 'Name and Type are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO assets (name, type, serial_number, location, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $type, $serial, $location, $status);
        $stmt->execute();
        $stmt->close();
        $message = t('asset_added');
    }
}

// --- Search / filter ---
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM assets WHERE name LIKE CONCAT('%', ?, '%') OR type LIKE CONCAT('%', ?, '%') OR location LIKE CONCAT('%', ?, '%') ORDER BY name");
    $stmt->bind_param('sss', $search, $search, $search);
    $stmt->execute();
    $assets = $stmt->get_result();
} else {
    $assets = $conn->query("SELECT * FROM assets ORDER BY name");
}

// Map DB status -> wireframe label/class (active=Online/green, faulty=Offline/red)
// "Maintenance" (orange) applies to assets with a pending schedule — see maintenance/schedule.php
function asset_status_label($status) {
    return match ($status) {
        'active' => [t('status_online'), 'online'],
        'faulty' => [t('status_offline_label'), 'offline'],
        'retired' => [t('status_retired'), 'maintenance'],
        default => [ucfirst($status), ''],
    };
}

$page_title = t('equipment_inventory');
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:16px;">
    <form method="GET" style="flex:1;">
        <input type="text" name="q" class="search-input" placeholder="<?php echo t('search_assets'); ?>" value="<?php echo htmlspecialchars($search); ?>">
    </form>
    <?php if (current_role() === 'admin'): ?>
        <button type="button" class="btn-add" onclick="document.getElementById('addAssetForm').style.display='block'"><?php echo t('add_asset'); ?></button>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if (current_role() === 'admin'): ?>
<div id="addAssetForm" class="panel" style="display:none; margin-bottom:20px;">
    <h2><?php echo t('add_new_asset'); ?></h2>
    <form method="POST">
        <input type="hidden" name="add_asset" value="1">
        <div class="form-group"><label><?php echo t('name'); ?></label><input type="text" name="name" required></div>
        <div class="form-group"><label><?php echo t('type'); ?></label><input type="text" name="type" placeholder="Computer, Router, Printer..." required></div>
        <div class="form-group"><label><?php echo t('serial_number'); ?></label><input type="text" name="serial_number"></div>
        <div class="form-group"><label><?php echo t('location'); ?></label><input type="text" name="location"></div>
        <div class="form-group">
            <label><?php echo t('status'); ?></label>
            <select name="status" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value="active"><?php echo t('status_online'); ?></option>
                <option value="faulty"><?php echo t('status_offline_label'); ?></option>
                <option value="retired"><?php echo t('status_retired'); ?></option>
            </select>
        </div>
        <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;"><?php echo t('save_asset'); ?></button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr><th><?php echo t('name'); ?></th><th><?php echo t('type'); ?></th><th><?php echo t('location'); ?></th><th><?php echo t('status'); ?></th></tr>
        </thead>
        <tbody>
            <?php if ($assets->num_rows === 0): ?>
                <tr><td colspan="4"><?php echo t('no_assets_found'); ?></td></tr>
            <?php else: ?>
                <?php while ($row = $assets->fetch_assoc()):
                    [$label, $class] = asset_status_label($row['status']); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><span class="status-pill status-<?php echo $class; ?>"><?php echo $label; ?></span></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
