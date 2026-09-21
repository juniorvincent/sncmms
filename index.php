<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';

// Logged-in users skip the landing page and go straight to their dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /sncmms/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title>SNCMMS — Smart Network & Computer Maintenance</title>
    <link rel="stylesheet" href="/sncmms/assets/css/style.css">
</head>
<body class="futuristic-body">

    <!-- Animated background: grid scan + drifting glow blobs -->
    <div class="fx-bg">
        <div class="fx-grid"></div>
        <div class="fx-blob fx-blob-1"></div>
        <div class="fx-blob fx-blob-2"></div>
        <div class="fx-blob fx-blob-3"></div>

        <!-- Floating device icons (pure SVG, no external images) -->
        <svg class="fx-icon fx-icon-1" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="12" rx="1.5" stroke="#2563EB" stroke-width="1.5"/><path d="M8 20h8M12 16v4" stroke="#2563EB" stroke-width="1.5" stroke-linecap="round"/></svg>
        <svg class="fx-icon fx-icon-2" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="1.8" fill="#16A34A"/><path d="M6 12a6 6 0 0112 0M3 12a9 9 0 0118 0" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round"/></svg>
        <svg class="fx-icon fx-icon-3" viewBox="0 0 24 24" fill="none"><path d="M4 6h16v10H4z" stroke="#F59E0B" stroke-width="1.5"/><path d="M4 6l8 6 8-6" stroke="#F59E0B" stroke-width="1.5"/></svg>
        <svg class="fx-icon fx-icon-4" viewBox="0 0 24 24" fill="none"><rect x="5" y="3" width="14" height="18" rx="2" stroke="#2563EB" stroke-width="1.5"/><circle cx="12" cy="17" r="0.8" fill="#2563EB"/></svg>
        <svg class="fx-icon fx-icon-5" viewBox="0 0 24 24" fill="none"><path d="M12 3v4M12 17v4M3 12h4M17 12h4" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="12" r="4" stroke="#16A34A" stroke-width="1.5"/></svg>
        <svg class="fx-icon fx-icon-6" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9" stroke="#F59E0B" stroke-width="1.5"/></svg>
    </div>

    <div class="fx-content">
        <div class="login-header" style="display:flex; justify-content:space-between; align-items:center; background:transparent;">
            <span>SNCMMS — Smart Network &amp; Computer Maintenance</span>
            <div class="lang-switch" style="margin:0;">
                <a href="/sncmms/includes/set_language.php?lang=en" style="color:#fff;<?php echo $_SESSION['lang']==='en'?'font-weight:700;':'opacity:.6;'; ?>">EN</a>
                <span style="color:#fff;">|</span>
                <a href="/sncmms/includes/set_language.php?lang=fr" style="color:#fff;<?php echo $_SESSION['lang']==='fr'?'font-weight:700;':'opacity:.6;'; ?>">FR</a>
            </div>
        </div>

        <div class="landing-hero fx-hero">
            <h1><?php echo t('landing_title'); ?></h1>
            <p><?php echo t('landing_subtitle'); ?></p>
            <a href="/sncmms/auth/login.php" class="btn-primary landing-cta"><?php echo t('landing_login'); ?></a>
            <p style="margin-top:14px;"><a href="/sncmms/tickets/track.php" style="font-size:13px; color:#93C5FD;"><?php echo t('landing_track'); ?> →</a></p>
        </div>

        <div class="landing-features">
            <div class="landing-feature fx-card">
                <div class="landing-feature-dot" style="background:#2563EB;"></div>
                <h3><?php echo t('landing_feature1_title'); ?></h3>
                <p><?php echo t('landing_feature1_text'); ?></p>
            </div>
            <div class="landing-feature fx-card">
                <div class="landing-feature-dot" style="background:#F59E0B;"></div>
                <h3><?php echo t('landing_feature2_title'); ?></h3>
                <p><?php echo t('landing_feature2_text'); ?></p>
            </div>
            <div class="landing-feature fx-card">
                <div class="landing-feature-dot" style="background:#16A34A;"></div>
                <h3><?php echo t('landing_feature3_title'); ?></h3>
                <p><?php echo t('landing_feature3_text'); ?></p>
            </div>
        </div>
    </div>

</body>
</html>
