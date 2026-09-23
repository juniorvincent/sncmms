<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login(); // User, Technician, or Admin can submit a ticket

$message = '';
$duplicate_warning = null;
$suggested_priority = null;

$assets_list = $conn->query("SELECT id, name FROM assets ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int) ($_POST['asset_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'other');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $priority = $_POST['priority'] ?? '';
    $impact = $_POST['impact'] ?? 'medium';
    $confirm_duplicate = isset($_POST['confirm_duplicate']);

    if ($asset_id <= 0 || $title === '' || $description === '' || !in_array($category, ticket_categories(), true)) {
        $message = t('fill_asset_and_description');
    } else {
        // --- Smart rule: duplicate detection (Week 5 PoC) ---
        $possible_dup = find_possible_duplicate_ticket($conn, $asset_id, $description);

        if ($possible_dup && !$confirm_duplicate) {
            $duplicate_warning = $possible_dup;
            $suggested_priority = $priority ?: suggest_ticket_priority($description);
        } else {
            // If the user didn't pick a priority, use the smart suggestion
            if ($priority === '') {
                $priority = suggest_ticket_priority($description);
            }

            // If no location given, default to the asset's own location
            if ($location === '') {
                $stmt = $conn->prepare("SELECT location FROM assets WHERE id = ?");
                $stmt->bind_param('i', $asset_id);
                $stmt->execute();
                $asset_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $location = $asset_row['location'] ?? '';
            }

            // --- Auto-assignment: least-loaded technician (Algorithm, Week 3) ---
            $technician_id = assign_best_technician($conn);
            $new_status = $technician_id ? 'pending' : 'open'; // Pending = assigned, not yet started by technician

            $stmt = $conn->prepare("INSERT INTO tickets (asset_id, reported_by, assigned_to, title, category, description, location, priority, impact, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisssssss', $asset_id, $_SESSION['user_id'], $technician_id, $title, $category, $description, $location, $priority, $impact, $new_status);
            $stmt->execute();
            $ticket_id = $stmt->insert_id;
            $stmt->close();

            // --- Help-desk style reference number + SLA target ---
            $reference_no = generate_ticket_reference($conn, $ticket_id);
            $sla_due_at = calculate_sla_due_date($priority);
            $stmt = $conn->prepare("UPDATE tickets SET reference_no = ?, sla_due_at = ? WHERE id = ?");
            $stmt->bind_param('ssi', $reference_no, $sla_due_at, $ticket_id);
            $stmt->execute();
            $stmt->close();

            $history_note = $technician_id
                ? 'Ticket created — auto-assigned to least-loaded technician'
                : 'Ticket created — no technician available, queued';
            $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, changed_by, change_note) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $ticket_id, $_SESSION['user_id'], $history_note);
            $stmt->execute();
            $stmt->close();

            if ($technician_id) {
                notify_user($conn, $technician_id, "New ticket $reference_no has been assigned to you.", "/sncmms/tickets/update.php?id=" . $ticket_id);
            }

            if (!empty($_FILES['attachment']['name'])) {
                handle_ticket_attachment_upload($conn, $ticket_id, (int) $_SESSION['user_id'], $_FILES['attachment']);
                // Upload errors are non-fatal here — the ticket itself was created successfully;
                // we don't want a bad attachment to block the whole submission.
            }

            header('Location: list.php?created=' . $ticket_id . '&ref=' . urlencode($reference_no));
            exit;
        }
    }
}

$page_title = t('submit_ticket_title');
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($duplicate_warning): ?>
    <div class="panel" style="border-left:4px solid #F59E0B; margin-bottom:20px;">
        <p style="font-weight:600; color:#F59E0B; margin-bottom:6px;">⚠ <?php echo t('possible_duplicate'); ?></p>
        <p style="font-size:13px; color:#64748B; margin-bottom:12px;">
            <?php echo t('ticket'); ?> #<?php echo $duplicate_warning['id']; ?> <?php echo t('possible_duplicate_desc'); ?>:
            "<?php echo htmlspecialchars($duplicate_warning['description']); ?>"
        </p>
        <form method="POST">
            <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($_POST['asset_id']); ?>">
            <input type="hidden" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($_POST['category'] ?? 'other'); ?>">
            <input type="hidden" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
            <input type="hidden" name="impact" value="<?php echo htmlspecialchars($_POST['impact'] ?? 'medium'); ?>">
            <input type="hidden" name="description" value="<?php echo htmlspecialchars($_POST['description']); ?>">
            <input type="hidden" name="priority" value="<?php echo htmlspecialchars($_POST['priority'] ?? ''); ?>">
            <input type="hidden" name="confirm_duplicate" value="1">
            <button type="submit" class="btn-add"><?php echo t('submit_anyway'); ?></button>
        </form>
    </div>
<?php endif; ?>

<p style="margin-bottom:14px; font-size:13px;">
    💡 <?php echo t('kb_suggestion_text'); ?> <a href="/sncmms/knowledge_base/list.php" style="color:#2563EB; font-weight:600;"><?php echo t('kb_suggestion_link'); ?></a>
</p>

<div class="panel" style="max-width:600px;">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label><?php echo t('ticket_title'); ?></label>
            <input type="text" name="title" required maxlength="150" placeholder="<?php echo t('ticket_title_placeholder'); ?>" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
        </div>
        <div class="form-group">
            <label><?php echo t('select_asset'); ?></label>
            <select name="asset_id" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value=""><?php echo t('choose_an_asset'); ?></option>
                <?php while ($a = $assets_list->fetch_assoc()): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('location'); ?> <span style="font-weight:400; color:#94A3B8;">(<?php echo t('optional_defaults_to_asset'); ?>)</span></label>
            <input type="text" name="location" maxlength="150" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
        </div>
        <div class="form-group">
            <label><?php echo t('category'); ?></label>
            <select name="category" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <?php foreach (ticket_categories() as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars(category_label($cat)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('description'); ?></label>
            <textarea name="description" rows="4" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label><?php echo t('impact'); ?></label>
            <select name="impact" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value="low"><?php echo t('impact_low'); ?></option>
                <option value="medium" selected><?php echo t('impact_medium'); ?></option>
                <option value="high"><?php echo t('impact_high'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('priority'); ?> <?php if ($suggested_priority) echo '(' . t('priority_suggested') . ': ' . t('priority_' . $suggested_priority) . ')'; ?></label>
            <select name="priority" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value=""><?php echo t('auto_suggest_priority'); ?></option>
                <option value="low"><?php echo t('priority_low'); ?></option>
                <option value="medium"><?php echo t('priority_medium'); ?></option>
                <option value="high"><?php echo t('priority_high'); ?></option>
                <option value="critical"><?php echo t('priority_critical'); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('attachment'); ?> <span style="font-weight:400; color:#94A3B8;">(<?php echo t('optional'); ?>, <?php echo t('attachment_hint'); ?>)</span></label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
        </div>

        <button type="submit" class="btn-primary" style="width:auto; padding:11px 26px;"><?php echo t('submit'); ?></button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
