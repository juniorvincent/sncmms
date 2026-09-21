<?php require_once __DIR__ . '/lang.php'; ?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title>SNCMMS<?php echo isset($page_title) ? ' — ' . htmlspecialchars($page_title) : ''; ?></title>
    <link rel="stylesheet" href="/sncmms/assets/css/style.css">
</head>
<body>
<div class="app-layout">

    <aside class="sidebar">
        <a href="/sncmms/index.php" class="brand" style="text-decoration:none; color:inherit; display:block;">SNCMMS</a>
        <nav>
            <?php
            // Highlight the current nav item based on the request path
            // (matches by folder+file, not just filename, since e.g.
            // assets_module/list.php and tickets/list.php share a basename)
            $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            function nav_link($href, $label, $current_path) {
                $active = (strpos($current_path, $href) !== false) ? ' active' : '';
                echo "<a href=\"$href\" class=\"$active\">$label</a>";
            }
            ?>
            <?php nav_link('/sncmms/admin/dashboard.php', t('nav_dashboard'), $current_path); ?>
            <?php nav_link('/sncmms/assets_module/list.php', t('nav_assets'), $current_path); ?>
            <?php nav_link('/sncmms/tickets/list.php', t('nav_tickets'), $current_path); ?>
            <?php nav_link('/sncmms/maintenance/schedule.php', t('nav_maintenance'), $current_path); ?>
            <?php nav_link('/sncmms/reports/dashboard_reports.php', t('nav_reports'), $current_path); ?>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <?php nav_link('/sncmms/admin/users.php', t('nav_users'), $current_path); ?>
                <?php nav_link('/sncmms/admin/scan_devices.php', t('nav_scan'), $current_path); ?>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="main-area">
        <header class="topheader">
            <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : ''; ?></h1>
            <div class="user-chip">
                <div class="lang-switch">
                    <a href="/sncmms/includes/set_language.php?lang=en" class="<?php echo $_SESSION['lang']==='en'?'lang-active':''; ?>">EN</a>
                    <span>|</span>
                    <a href="/sncmms/includes/set_language.php?lang=fr" class="<?php echo $_SESSION['lang']==='fr'?'lang-active':''; ?>">FR</a>
                </div>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></div>
                    <span><?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?>)</span>
                    <a href="/sncmms/auth/logout.php" class="logout-link"><?php echo t('logout'); ?></a>
                <?php endif; ?>
            </div>
        </header>

        <main class="page-content">
