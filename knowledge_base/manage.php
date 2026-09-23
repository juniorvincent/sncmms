<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_role(['admin']); // Admin creates/edits/deletes; Technician/User only browse (list.php/view.php)

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$article = null;
$message = '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM kb_articles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $article = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$article) {
        die('Article not found.');
    }
}

// --- Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_article'])) {
    $stmt = $conn->prepare("DELETE FROM kb_articles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: list.php?deleted=1');
    exit;
}

// --- Create or Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_article'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'other');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '' || !in_array($category, ticket_categories(), true)) {
        $message = t('fill_all_fields');
    } elseif ($id > 0) {
        $stmt = $conn->prepare("UPDATE kb_articles SET title = ?, category = ?, content = ? WHERE id = ?");
        $stmt->bind_param('sssi', $title, $category, $content, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: view.php?id=' . $id . '&updated=1');
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO kb_articles (title, category, content, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('sssi', $title, $category, $content, $_SESSION['user_id']);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        header('Location: view.php?id=' . $new_id . '&created=1');
        exit;
    }
}

$page_title = $article ? t('edit_article') : t('new_article');
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="panel" style="max-width:680px;">
    <form method="POST">
        <input type="hidden" name="save_article" value="1">
        <?php if ($article): ?><input type="hidden" name="id" value="<?php echo $article['id']; ?>"><?php endif; ?>

        <div class="form-group">
            <label><?php echo t('ticket_title'); ?></label>
            <input type="text" name="title" required maxlength="200" value="<?php echo htmlspecialchars($article['title'] ?? ($_POST['title'] ?? '')); ?>" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
        </div>
        <div class="form-group">
            <label><?php echo t('category'); ?></label>
            <select name="category" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <?php foreach (ticket_categories() as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($article['category'] ?? '') === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars(category_label($cat)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo t('content'); ?></label>
            <textarea name="content" rows="10" required style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;"><?php echo htmlspecialchars($article['content'] ?? ($_POST['content'] ?? '')); ?></textarea>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <button type="submit" class="btn-primary" style="width:auto; padding:11px 26px;"><?php echo t('save'); ?></button>
            <?php if ($article): ?>
                <span></span>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($article): ?>
        <form method="POST" onsubmit="return confirm('<?php echo t('confirm_delete_article'); ?>');" style="margin-top:16px; padding-top:16px; border-top:1px solid #E2E8F0;">
            <input type="hidden" name="delete_article" value="1">
            <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
            <button type="submit" style="background:none; border:none; color:#DC2626; font-size:13px; cursor:pointer; padding:0;"><?php echo t('delete_article'); ?></button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
