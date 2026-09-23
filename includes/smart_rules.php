<?php
// ============================================================
// Smart / "AI-lite" rules — Week 5 PoC
// Rule-based logic (see AI Strategy doc, Section 1.2, for why
// this is rule-based rather than a trained ML model).
// ============================================================

/**
 * Predictive maintenance flag.
 * Flags an asset as "at risk" if it has had more than $threshold
 * tickets logged within the last $days days.
 *
 * @param mysqli $conn
 * @param int $days       rolling window size (default 90)
 * @param int $threshold  ticket count above which an asset is flagged (default 3)
 * @return array of ['id' => .., 'name' => .., 'ticket_count' => ..]
 */
function get_at_risk_assets(mysqli $conn, int $days = 90, int $threshold = 3): array {
    $sql = "
        SELECT a.id, a.name, COUNT(t.id) AS ticket_count
        FROM assets a
        JOIN tickets t ON t.asset_id = a.id
        WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY a.id, a.name
        HAVING COUNT(t.id) > ?
        ORDER BY ticket_count DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $days, $threshold);
    $stmt->execute();
    $result = $stmt->get_result();

    $flagged = [];
    while ($row = $result->fetch_assoc()) {
        $flagged[] = $row;
    }
    $stmt->close();
    return $flagged;
}

/**
 * Ticket priority suggestion.
 * Scans a ticket description for urgency keywords and suggests
 * a priority level. This is a SUGGESTION only — the user can
 * override it in the ticket form.
 *
 * @param string $description
 * @return string 'high' | 'medium' | 'low'
 */
function suggest_ticket_priority(string $description): string {
    $text = strtolower($description);

    $critical_keywords = ['down', "won't turn on", 'wont turn on', 'no power', 'smoke', 'sparking', 'completely dead', 'critical', 'entire network', 'all users', 'security breach'];
    $high_keywords = ['urgent', 'not working', 'cannot access', "can't access", 'broken'];
    $low_keywords  = ['cosmetic', 'minor', 'small scratch', 'slightly slow', 'when convenient'];

    foreach ($critical_keywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return 'critical';
        }
    }
    foreach ($high_keywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return 'high';
        }
    }
    foreach ($low_keywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return 'low';
        }
    }
    return 'medium';
}

/**
 * Duplicate ticket detection.
 * Warns if a new ticket description closely matches an existing
 * OPEN ticket for the same asset (using PHP's built-in similar_text).
 *
 * @param mysqli $conn
 * @param int $asset_id
 * @param string $new_description
 * @param float $similarity_threshold percent similarity to flag as duplicate (default 70)
 * @return array|null the matching existing ticket row, or null if no duplicate found
 */
function find_possible_duplicate_ticket(mysqli $conn, int $asset_id, string $new_description, float $similarity_threshold = 70.0): ?array {
    $stmt = $conn->prepare("SELECT id, description FROM tickets WHERE asset_id = ? AND status IN ('open','pending','in_progress')");
    $stmt->bind_param('i', $asset_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        similar_text(strtolower($new_description), strtolower($row['description']), $percent);
        if ($percent >= $similarity_threshold) {
            $stmt->close();
            return $row;
        }
    }
    $stmt->close();
    return null;
}

/**
 * Generate a formal help-desk style ticket reference number,
 * e.g. TK-2026-000123 — similar in spirit to the reference numbers
 * used by Cameroonian public-service portals (a citizen/employee
 * gets a reference number to track their request).
 *
 * @param mysqli $conn
 * @param int $ticket_id  the auto-increment id just inserted
 * @return string
 */
function generate_ticket_reference(mysqli $conn, int $ticket_id): string {
    $year = date('Y');
    return 'TK-' . $year . '-' . str_pad((string) $ticket_id, 6, '0', STR_PAD_LEFT);
}

/**
 * SLA (service-level) target: how long a ticket should take to
 * resolve based on its priority. Returns the due DATETIME string.
 */
function calculate_sla_due_date(string $priority): string {
    $hours = match ($priority) {
        'critical' => 2,
        'high' => 4,
        'medium' => 24,
        'low' => 72,
        default => 24,
    };
    return date('Y-m-d H:i:s', strtotime("+$hours hours"));
}

/**
 * SLA countdown for display: returns ['label' => "01h 32min", 'breached' => bool]
 * or null if the ticket has no SLA due date (e.g. already resolved tickets
 * still show their last computed value — this just formats it).
 */
function sla_status(?string $sla_due_at, string $status): ?array {
    if (!$sla_due_at || in_array($status, ['resolved', 'closed'], true)) {
        return null; // no active countdown once resolved/closed
    }
    $diff_seconds = strtotime($sla_due_at) - time();
    $breached = $diff_seconds < 0;
    $abs = abs($diff_seconds);
    $h = intdiv($abs, 3600);
    $m = intdiv($abs % 3600, 60);
    $label = sprintf('%02dh %02dmin', $h, $m);
    return ['label' => $label, 'breached' => $breached];
}

/** Standard ticket category codes offered in the submission form (translated on display via category_label()). */
function ticket_categories(): array {
    return ['hardware', 'network', 'printer', 'software', 'other'];
}

/** Translated label for a category code. */
function category_label(string $code): string {
    return t('category_' . $code);
}

/**
 * Auto-assign a new ticket to the technician with the fewest
 * currently-open tickets (matches the Ticket Assignment flowchart
 * and Algorithm Design doc, Section 1). Returns the technician's
 * user id, or null if no technician exists yet (ticket stays
 * unassigned / queued in that case).
 *
 * @param mysqli $conn
 * @return int|null
 */
function assign_best_technician(mysqli $conn): ?int {
    $sql = "
        SELECT u.id,
               COUNT(t.id) AS open_count
        FROM users u
        LEFT JOIN tickets t
               ON t.assigned_to = u.id
              AND t.status IN ('open', 'pending', 'in_progress')
        WHERE u.role = 'technician'
        GROUP BY u.id
        ORDER BY open_count ASC, u.id ASC
        LIMIT 1
    ";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return (int) $row['id'];
    }
    return null; // no technician available — queue it
}

/**
 * Add a comment to a ticket (Technician/Admin only — enforced by
 * the calling page's require_role(), not here).
 *
 * @param mysqli $conn
 * @param int $ticket_id
 * @param int $user_id       who is commenting
 * @param string $comment
 * @param bool $is_resolution  true if this comment is THE resolution comment
 */
function add_ticket_comment(mysqli $conn, int $ticket_id, int $user_id, string $comment, bool $is_resolution = false): void {
    $stmt = $conn->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, is_resolution) VALUES (?, ?, ?, ?)");
    $flag = $is_resolution ? 1 : 0;
    $stmt->bind_param('iisi', $ticket_id, $user_id, $comment, $flag);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get all comments for a ticket, oldest first, with the author's
 * name and role attached (so the UI can label "Technician" etc.).
 */
function get_ticket_comments(mysqli $conn, int $ticket_id): array {
    $stmt = $conn->prepare("
        SELECT c.id, c.comment, c.is_resolution, c.created_at, u.name, u.role
        FROM ticket_comments c JOIN users u ON u.id = c.user_id
        WHERE c.ticket_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();
    return $comments;
}

/** Whether a ticket already has a resolution comment recorded. */
function has_resolution_comment(mysqli $conn, int $ticket_id): bool {
    $stmt = $conn->prepare("SELECT id FROM ticket_comments WHERE ticket_id = ? AND is_resolution = 1 LIMIT 1");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $found;
}

/** Problem reference number, e.g. P001, P002... */
function generate_problem_reference(int $problem_id): string {
    return 'P' . str_pad((string) $problem_id, 3, '0', STR_PAD_LEFT);
}

/**
 * Create a notification for a user (shown via the 🔔 icon).
 */
function notify_user(mysqli $conn, int $user_id, string $message, ?string $link = null): void {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user_id, $message, $link);
    $stmt->execute();
    $stmt->close();
}

/** Unread notification count for the navbar badge. */
function unread_notification_count(mysqli $conn, int $user_id): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['c'];
}

/**
 * Check for tickets that have just breached their SLA and notify
 * every Admin once each (flagged via sla_breach_notified so this
 * doesn't re-fire every time the dashboard loads). Since this
 * project has no cron/background job, this is called opportunistically
 * whenever an Admin loads the dashboard — acceptable for a university
 * project, though a real deployment would use a scheduled task instead.
 */
function check_and_notify_sla_breaches(mysqli $conn): void {
    $result = $conn->query("
        SELECT id, reference_no FROM tickets
        WHERE sla_due_at IS NOT NULL
          AND sla_due_at < NOW()
          AND status NOT IN ('resolved','closed')
          AND sla_breach_notified = 0
    ");
    if (!$result || $result->num_rows === 0) {
        return;
    }
    $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);

    while ($ticket = $result->fetch_assoc()) {
        $ref = $ticket['reference_no'] ?? ('#' . $ticket['id']);
        foreach ($admins as $admin) {
            notify_user($conn, (int) $admin['id'], "Ticket $ref has exceeded its SLA.", "/sncmms/tickets/update.php?id=" . $ticket['id']);
        }
        $stmt = $conn->prepare("UPDATE tickets SET sla_breach_notified = 1 WHERE id = ?");
        $stmt->bind_param('i', $ticket['id']);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Handle a single uploaded file attachment for a ticket.
 * Validates extension + size, stores it under a random filename
 * (never the original name, to avoid path traversal / overwrite
 * tricks), and records it in the attachments table.
 *
 * @param mysqli $conn
 * @param int $ticket_id
 * @param int $user_id
 * @param array $file  one entry from $_FILES, e.g. $_FILES['attachment']
 * @return string|null  an error message, or null on success (including "no file selected")
 */
function handle_ticket_attachment_upload(mysqli $conn, int $ticket_id, int $user_id, array $file): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // nothing selected — not an error
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed (error code ' . $file['error'] . ').';
    }

    $max_bytes = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_bytes) {
        return 'File is too large (max 5MB).';
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        return 'File type not allowed. Allowed: ' . implode(', ', $allowed_ext) . '.';
    }

    $upload_dir = __DIR__ . '/../uploads/';
    $stored_name = bin2hex(random_bytes(16)) . '.' . $ext; // never trust/reuse the original filename on disk

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $stored_name)) {
        return 'Could not save the uploaded file.';
    }

    $stmt = $conn->prepare("INSERT INTO attachments (ticket_id, uploaded_by, original_name, stored_name, file_size) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iissi', $ticket_id, $user_id, $file['name'], $stored_name, $file['size']);
    $stmt->execute();
    $stmt->close();

    return null;
}

/** All attachments for a ticket, newest first. */
function get_ticket_attachments(mysqli $conn, int $ticket_id): array {
    $stmt = $conn->prepare("
        SELECT a.id, a.original_name, a.stored_name, a.file_size, a.uploaded_at, u.name AS uploader_name
        FROM attachments a JOIN users u ON u.id = a.uploaded_by
        WHERE a.ticket_id = ? ORDER BY a.uploaded_at DESC
    ");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
