<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lang.php';
require_role(['admin']);

$message = '';
$discovered = [];
$scan_error = '';

// --- Handle "Add as Asset" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discovered'])) {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'Network Device');
    $ip = trim($_POST['ip_address'] ?? '');
    $mac = trim($_POST['mac_address'] ?? '');

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO assets (name, type, ip_address, mac_address, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param('ssss', $name, $type, $ip, $mac);
        $stmt->execute();
        $stmt->close();
        $message = "Added \"$name\" ($ip) to inventory.";
    }
}

// --- Run the scan ---
// NOTE: this reads the Windows ARP cache (devices this PC has recently
// talked to on the LAN) via `arp -a`. It is NOT a full active network
// scan (that would need a tool like nmap installed separately, which
// this project intentionally avoids to keep the stack dependency-free
// per the Week 5 "no paid/extra tools" decision).
if (isset($_GET['run_scan'])) {
    $is_windows = stripos(PHP_OS, 'WIN') === 0;

    if ($is_windows) {
        $output = shell_exec('arp -a 2>&1');
    } else {
        $output = shell_exec('arp -a 2>&1'); // also works on Linux/Mac, different format
    }

    if ($output === null) {
        $scan_error = 'Could not run the arp command. shell_exec() may be disabled on this server (check php.ini disable_functions).';
    } else {
        // Match lines like: 192.168.1.5   aa-bb-cc-dd-ee-ff   dynamic  (Windows)
        // or: ? (192.168.1.5) at aa:bb:cc:dd:ee:ff  (Linux/Mac)
        preg_match_all(
            '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).{0,20}?([0-9a-fA-F]{2}([-:])[0-9a-fA-F]{2}(\3[0-9a-fA-F]{2}){4})/',
            $output,
            $matches,
            PREG_SET_ORDER
        );

        // Existing known IPs/MACs, so we only show what's NOT already in inventory
        $known = [];
        $res = $conn->query("SELECT ip_address, mac_address FROM assets WHERE ip_address IS NOT NULL OR mac_address IS NOT NULL");
        while ($row = $res->fetch_assoc()) {
            if ($row['ip_address']) $known[] = $row['ip_address'];
            if ($row['mac_address']) $known[] = strtolower($row['mac_address']);
        }

        $seen_ips = [];
        foreach ($matches as $m) {
            $ip = $m[1];
            $mac = $m[2];
            if (in_array($ip, $seen_ips, true)) continue; // de-dupe
            $seen_ips[] = $ip;

            $already_known = in_array($ip, $known, true) || in_array(strtolower($mac), array_map('strtolower', $known), true);
            if (!$already_known) {
                $discovered[] = ['ip' => $ip, 'mac' => $mac];
            }
        }
    }
}

$page_title = t('nav_scan');
include __DIR__ . '/../includes/header.php';
?>

<?php if ($message): ?>
    <p style="margin-bottom:16px; color:#16A34A; font-weight:600;"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="panel" style="margin-bottom:20px;">
    <p style="font-size:13px; color:#64748B; margin-bottom:14px;">
        This scans your computer's ARP cache to find devices that have recently communicated on the local
        network, and lists any that aren't already in your asset inventory. It only sees devices on the
        <strong>same local network</strong> as this server, and only ones this PC has recently talked to —
        it is not a full active network sweep.
    </p>
    <a href="?run_scan=1" class="btn-add">Run Scan</a>
</div>

<?php if ($scan_error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($scan_error); ?></div>
<?php endif; ?>

<?php if (isset($_GET['run_scan'])): ?>
<div class="panel">
    <h2>Discovered Devices (not yet in inventory)</h2>
    <?php if (empty($discovered)): ?>
        <p style="font-size:13px; color:#64748B;">No new devices found — everything visible on the network is already in your inventory, or the ARP cache is empty (try browsing to a few devices/websites first, then re-scan).</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th>IP Address</th><th>MAC Address</th><th>Add to Inventory</th></tr></thead>
            <tbody>
                <?php foreach ($discovered as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['ip']); ?></td>
                        <td><?php echo htmlspecialchars($d['mac']); ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="add_discovered" value="1">
                                <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($d['ip']); ?>">
                                <input type="hidden" name="mac_address" value="<?php echo htmlspecialchars($d['mac']); ?>">
                                <input type="text" name="name" placeholder="Device name" required style="padding:5px 8px; border:1px solid #E2E8F0; border-radius:4px; font-size:12px; width:130px;">
                                <select name="type" style="padding:5px; border:1px solid #E2E8F0; border-radius:4px; font-size:12px;">
                                    <option>Computer</option>
                                    <option>Network Device</option>
                                    <option>Printer</option>
                                    <option>Other</option>
                                </select>
                                <button type="submit" class="btn-add" style="padding:5px 12px; font-size:12px;">Add</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
