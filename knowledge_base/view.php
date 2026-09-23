<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT a.*, u.name AS author_name
    FROM kb_articles a LEFT JOIN users u ON u.id = a.created_by
    WHERE a.id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
    die('Article not found.');
}

$page_title = $article['title'];
include __DIR__ . '/../includes/header.php';
?>

<p style="margin-bottom:14px;"><a href="list.php" style="font-size:13px; color:#2563EB;">← <?php echo t('nav_knowledge_base'); ?></a></p>

<div class="panel" style="max-width:720px;">
    <span class="status-pill status-maintenance" style="margin-bottom:12px; display:inline-block;"><?php echo htmlspecialchars(category_label($article['category'])); ?></span>
    <h1 style="font-size:22px; color:#1E293B; margin-bottom:10px;"><?php echo htmlspecialchars($article['title']); ?></h1>
    <p style="font-size:12px; color:#94A3B8; margin-bottom:20px;">
        <?php if ($article['author_name']): ?><?php echo t('by'); ?> <?php echo htmlspecialchars($article['author_name']); ?> — <?php endif; ?>
        <?php echo t('updated'); ?> <?php echo date('d/m/Y', strtotime($article['updated_at'])); ?>
    </p>
    <div style="font-size:14px; color:#1E293B; line-height:1.7; white-space:pre-line;"><?php echo nl2br(htmlspecialchars($article['content'])); ?></div>

    <?php if (current_role() === 'admin'): ?>
        <div style="margin-top:24px; padding-top:16px; border-top:1px solid #E2E8F0;">
            <a href="manage.php?id=<?php echo $article['id']; ?>" class="btn-add" style="padding:8px 16px; font-size:13px;"><?php echo t('edit'); ?></a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
