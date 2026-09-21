<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';

// If already logged in, go straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /sncmms/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = t('fill_both_fields');
    } else {
        // Use a prepared statement to prevent SQL injection (see TC12 in the test plan)
        $stmt = $conn->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: /sncmms/admin/dashboard.php');
            exit;
        } else {
            $error = t('invalid_credentials');
        }
    }
}

// Login has no sidebar (user isn't authenticated yet), so it uses its own
// minimal layout instead of includes/header.php + footer.php.
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <title>SNCMMS — <?php echo t('login_title'); ?></title>
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

    <div class="login-box">
        <h1><?php echo t('login_title'); ?></h1>

        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email"><?php echo t('email'); ?></label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password"><?php echo t('password'); ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary"><?php echo t('log_in'); ?></button>
        </form>
    </div>

</body>
</html>
