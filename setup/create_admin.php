<?php
// ============================================================
// ONE-TIME SETUP SCRIPT.
// Run this once in your browser (http://localhost/sncmms/setup/create_admin.php)
// to create your first admin account, then DELETE this file —
// leaving it on a live server would let anyone create an admin account.
// ============================================================

require_once __DIR__ . '/../config/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $message = 'Please fill all fields; password must be at least 6 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "admin")');
        $stmt->bind_param('sss', $name, $email, $hash);
        if ($stmt->execute()) {
            $message = 'Admin account created. You can now delete this file and log in at /sncmms/auth/login.php';
        } else {
            $message = 'Error: ' . $stmt->error . ' (maybe this email already exists?)';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html><head><title>Create Admin Account</title></head>
<body style="font-family:Arial; max-width:400px; margin:60px auto;">
<h2>Create First Admin Account</h2>
<?php if ($message): ?><p style="color:#d93025;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="POST">
    <p><label>Name<br><input type="text" name="name" required style="width:100%;padding:8px;"></label></p>
    <p><label>Email<br><input type="email" name="email" required style="width:100%;padding:8px;"></label></p>
    <p><label>Password<br><input type="password" name="password" required style="width:100%;padding:8px;"></label></p>
    <button type="submit" style="padding:10px 20px;">Create Admin</button>
</form>
</body></html>
