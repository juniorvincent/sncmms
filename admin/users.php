<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']); // Step "Is current session role = Admin?" from the flowchart — enforced here

$message = '';
$error = '';

// --- Create a new user (Technician or User) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    $allowed_roles = ['user', 'technician', 'admin'];
    if ($name === '' || $email === '' || strlen($password) < 6 || !in_array($role, $allowed_roles, true)) {
        $error = 'Please fill all fields correctly (password must be at least 6 characters).';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $error = 'A user with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            $stmt->execute();
            $stmt->close();
            $message = "User \"$name\" created with role \"$role\".";
        }
        $check->close();
    }
}

// --- Change an existing user's role (User <-> Technician), per the role-change flowchart ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $target_user_id = (int) $_POST['user_id'];
    $new_role = $_POST['new_role'] ?? '';
    $confirmed = isset($_POST['confirmed']); // "Confirm change?" step in the flowchart

    if (!in_array($new_role, ['user', 'technician'], true)) {
        $error = 'Invalid role.';
    } elseif ($target_user_id === (int) current_user_id()) {
        $error = 'You cannot change your own role.';
    } elseif (!$confirmed) {
        $error = 'Change not confirmed — no action taken.'; // "Cancel — no change made" branch
    } else {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param('i', $target_user_id);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$target) {
            $error = 'User not found.';
        } else {
            $old_role = $target['role'];

            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $new_role, $target_user_id);
            $stmt->execute();
            $stmt->close();

            // "Log change to audit trail" step
            $stmt = $conn->prepare("INSERT INTO role_audit_log (target_user_id, changed_by, old_role, new_role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiss', $target_user_id, $_SESSION['user_id'], $old_role, $new_role);
            $stmt->execute();
            $stmt->close();

            $message = "Role updated: $old_role → $new_role.";
        }
    }
}

$users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");

$page_title = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Create User panel -->
<div class="panel" style="max-width:600px; margin-bottom:24px;">
    <h2>Create New User</h2>
    <form method="POST">
        <input type="hidden" name="create_user" value="1">
        <div class="form-group"><label>Name</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="6"></div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:5px;">
                <option value="user">User</option>
                <option value="technician">Technician</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">Create User</button>
    </form>
</div>

<!-- User list + role change -->
<div class="panel">
    <h2>All Users</h2>
    <table class="data-table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Change Role (User ↔ Technician)</th></tr>
        </thead>
        <tbody>
            <?php while ($u = $users->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <span class="status-pill" style="background:<?php
                            echo $u['role'] === 'admin' ? '#0F172A' : ($u['role'] === 'technician' ? '#16A34A' : '#2563EB');
                        ?>;"><?php echo ucfirst($u['role']); ?></span>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'user' || $u['role'] === 'technician'): ?>
                            <?php $target_role = $u['role'] === 'user' ? 'technician' : 'user'; ?>
                            <form method="POST" onsubmit="return confirm('Change <?php echo htmlspecialchars($u['name']); ?> from <?php echo $u['role']; ?> to <?php echo $target_role; ?>?');" style="display:inline;">
                                <input type="hidden" name="change_role" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="new_role" value="<?php echo $target_role; ?>">
                                <input type="hidden" name="confirmed" value="1">
                                <button type="submit" class="btn-add" style="padding:5px 12px; font-size:12px;">
                                    Make <?php echo ucfirst($target_role); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="font-size:12px; color:#64748B;">— (Admin role not toggled here)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
