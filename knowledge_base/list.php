<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../includes/smart_rules.php';
require_login(); // all roles can browse/search the knowledge base

$search = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql = "SELECT id, title, category, content, updated_at FROM kb_articles WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%'))";
    $params[] = $search; $params[] = $search;
    $types .= 'ss';
}
if ($category_filter !== '' && in_array($category_filter, ticket_categories(), true)) {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= 's';
}
$sql .= " ORDER BY title ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $bind_args = [$types];
    foreach ($params as $key => $value) {
        $bind_args[] = &$params[$key]; // mysqli::bind_param requires references
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
}
$stmt->execute();
$articles = $stmt->get_result();

$page_title = t('nav_knowledge_base');
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:16px; flex-wrap:wrap;">
    <form method="GET" style="display:flex; gap:10px; flex:1; min-width:260px;">
        <input type="text" name="q" class="search-input" placeholder="<?php echo t('kb_search_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
        <select name="category" onchange="this.form.submit()" style="padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
            <option value=""><?php echo t('all_categories'); ?></option>
            <?php foreach (ticket_categories() as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars(category_label($cat)); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-add"><?php echo t('search'); ?></button>
    </form>
    <?php if (current_role() === 'admin'): ?>
        <a href="manage.php" class="btn-add">+ <?php echo t('new_article'); ?></a>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
    <?php if ($articles->num_rows === 0): ?>
        <p style="color:#64748B; font-size:13px;"><?php echo t('no_articles_found'); ?></p>
    <?php else: ?>
        <?php while ($a = $articles->fetch_assoc()): ?>
            <a href="view.php?id=<?php echo $a['id']; ?>" class="panel" style="text-decoration:none; color:inherit; display:block;">
                <span class="status-pill status-maintenance" style="margin-bottom:10px; display:inline-block;"><?php echo htmlspecialchars(category_label($a['category'])); ?></span>
                <h3 style="font-size:15px; color:#1E293B; margin-bottom:8px;"><?php echo htmlspecialchars($a['title']); ?></h3>
                <p style="font-size:13px; color:#64748B;"><?php echo htmlspecialchars(mb_strimwidth($a['content'], 0, 110, '...')); ?></p>
            </a>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
