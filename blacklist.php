<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');
$success = "";
$error = "";

$durations = [
    "1 Hour"    => 3600,
    "6 Hours"   => 21600,
    "1 Day"     => 86400,
    "3 Days"    => 259200,
    "1 Week"    => 604800,
    "2 Weeks"   => 1209600,
    "1 Month"   => 2592000,
    "3 Months"  => 7776000,
    "1 Year"    => 31536000,
    "Permanent" => null
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['blacklist_car'])) {
    $car_id = intval($_POST['car_id']);
    $reason = trim($_POST['reason']);
    $duration = $_POST['duration'] ?? 'Permanent';

    if (empty($reason)) {
        $error = "Reason is required.";
    } else {
        $stmt = $conn->prepare("SELECT plate_number FROM cars WHERE id = ?");
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $car_row = $stmt->get_result()->fetch_assoc();

        if ($car_row) {
            $plate = $car_row['plate_number'];
            $chk = $conn->prepare("SELECT id FROM blacklist WHERE plate_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $chk->bind_param("s", $plate);
            $chk->execute();

            if ($chk->get_result()->num_rows > 0) {
                $error = "This plate is already actively blacklisted.";
            } else {
                $expires_at = null;
                if (isset($durations[$duration]) && $durations[$duration] !== null) {
                    $expires_at = date('Y-m-d H:i:s', time() + $durations[$duration]);
                }
                $added_by = $_SESSION['user_id'];
                $ins = $conn->prepare("INSERT INTO blacklist (plate_number, reason, expires_at, added_by, duration_label) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("sssis", $plate, $reason, $expires_at, $added_by, $duration);

                if ($ins->execute()) {
                    $success = "Plate " . htmlspecialchars($plate) . " blacklisted for " . htmlspecialchars($duration) . ".";
                }
            }
        }
    }
}

if ($is_admin && isset($_GET['remove'])) {
    $id = intval($_GET['remove']);
    $conn->query("DELETE FROM blacklist WHERE id = $id");
    header("Location: blacklist.php");
    exit;
}

$search = "";
$students_found = null;
$students_count = 0;
$searched = false;

if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $searched = true;
    $like = "%$search%";

    $stmt = $conn->prepare("
        SELECT s.id, s.full_name, s.student_id, s.stage, s.class, s.track, s.phone,
               (SELECT COUNT(*) FROM cars WHERE student_id = s.id) AS car_count
        FROM students s
        WHERE s.full_name LIKE ? OR s.student_id LIKE ?
        ORDER BY s.full_name ASC
        LIMIT 20
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $students_found = $stmt->get_result();
    $students_count = $students_found->num_rows;
}

$conn->query("DELETE FROM blacklist WHERE expires_at IS NOT NULL AND expires_at <= NOW()");

$list = $conn->query("
    SELECT b.id, b.plate_number, b.reason, b.date_added, b.expires_at, b.duration_label,
           u.username AS added_by_username,
           s.full_name, s.student_id, s.stage, s.class, s.track
    FROM blacklist b
    LEFT JOIN cars c ON b.plate_number = c.plate_number
    LEFT JOIN students s ON c.student_id = s.id
    LEFT JOIN users u ON b.added_by = u.id
    ORDER BY b.date_added DESC
");

function isPlateBlacklisted($conn, $plate) {
    $stmt = $conn->prepare("SELECT reason, expires_at FROM blacklist WHERE plate_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->bind_param("s", $plate);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows > 0) return $r->fetch_assoc();
    return false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blacklist - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .sidebar nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .info-note { background: #e0f2fe; color: #075985; padding: 14px 18px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; border: 1px solid #bae6fd; }
        .search-card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .search-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 14px; }
        .search-form { display: flex; gap: 12px; }
        .search-form input { flex: 1; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; font-family: inherit; }
        .search-form input:focus { outline: none; border-color: #0f172a; }
        .search-form button { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .student-result { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 14px; }
        .student-result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px; }
        .student-info-line { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .student-avatar { width: 44px; height: 44px; border-radius: 50%; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px; flex-shrink: 0; }
        .student-name-lg { font-size: 16px; font-weight: 600; }
        .student-id-tag { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .stage-badge { display: inline-block; background: #f1f5f9; color: #0f172a; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .class-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-programming { background: #dbeafe; color: #1e40af; }
        .track-networking { background: #dcfce7; color: #166534; }
        .track-network { background: #dcfce7; color: #166534; }
        .view-profile-btn { background: #eff6ff; color: #2563eb; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500; }
        .car-mini-list { display: grid; gap: 10px; }
        .car-mini { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 10px; gap: 12px; flex-wrap: wrap; }
        .car-mini.blacklisted { background: #fef2f2; border-color: #fecaca; }
        .car-mini .plate-tag { font-family: monospace; background: #0f172a; color: #fff; padding: 5px 10px; border-radius: 5px; font-size: 13px; letter-spacing: 1px; }
        .car-mini.blacklisted .plate-tag { background: #dc2626; }
        .car-mini .car-details { font-size: 13px; color: #475569; }
        .badge-blocked-sm { display: inline-block; background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; margin-left: 8px; }
        .badge-locked { display: inline-block; background: #f1f5f9; color: #64748b; padding: 6px 12px; border-radius: 7px; font-size: 12px; font-weight: 500; }
        .btn-blacklist { background: #dc2626; color: #fff; padding: 7px 14px; border: none; border-radius: 7px; font-size: 12.5px; font-weight: 600; cursor: pointer; }
        .btn-blacklist:hover { background: #b91c1c; }
        .btn-unblock { background: #16a34a; color: #fff; padding: 7px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 600; text-decoration: none; display: inline-block; }
        .blacklist-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; }
        .blacklist-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .plate { font-family: monospace; background: #dc2626; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; letter-spacing: 1px; }
        .name-cell { font-weight: 500; color: #0f172a; }
        .meta-cell { font-size: 12px; color: #64748b; margin-top: 2px; }
        .unknown { color: #94a3b8; font-style: italic; font-size: 13px; }
        .btn-remove { color: #dc2626; text-decoration: none; font-size: 13px; font-weight: 500; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }
        .no-match { background: #fff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; color: #64748b; }
        .duration-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 5px; font-size: 11.5px; font-weight: 600; }
        .duration-badge.perm { background: #fee2e2; color: #991b1b; }
        .expires-info { font-size: 11.5px; color: #64748b; margin-top: 3px; }
        .expires-info.soon { color: #dc2626; font-weight: 600; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal { background: #fff; padding: 28px; border-radius: 14px; max-width: 460px; width: 100%; }
        .modal h3 { font-size: 17px; margin-bottom: 8px; }
        .modal .plate-preview { font-family: monospace; background: #0f172a; color: #fff; padding: 8px 14px; border-radius: 6px; display: inline-block; font-size: 14px; letter-spacing: 1px; margin-bottom: 16px; }
        .modal label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; margin-top: 14px; }
        .modal input[type="text"], .modal select { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; }
        .modal input[type="text"]:focus, .modal select:focus { outline: none; border-color: #0f172a; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; padding: 11px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .modal-btn-confirm { background: #dc2626; color: #fff; padding: 11px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
    </style>
    <script>
    (function() {
        try {
            var saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') document.documentElement.classList.add('dark-mode');
        } catch(e) {}
    })();
    </script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span><?php echo $is_admin ? 'Computer Sciences' : 'Guard Panel'; ?></span></div>
        </div>
        <div style="padding: 12px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Theme</span>
            <button onclick="toggleTheme()" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="<?php echo $is_admin ? 'dashboard.php' : 'guard_dashboard.php'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <?php if ($is_admin): ?>
                <div class="nav-label">Management</div>
                <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
                <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Bulk Import</a>
                <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
                <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
                <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Promote Students</a>
            <?php endif; ?>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
            <?php if ($is_admin): ?>
                <div class="nav-label">Account</div>
                <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Manage Users</a>
                <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Change Password</a>
            <?php endif; ?>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Blacklist Management</h1>
        <p class="page-sub">Search for a student, then blacklist any car with a time limit</p>

        <?php if ($is_guard): ?>
            <div class="info-note">You can blacklist a car for a limited time. Only an administrator can remove a plate permanently.</div>
        <?php endif; ?>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="search-card">
            <h3>Search Student</h3>
            <form method="GET" class="search-form">
                <input type="text" name="q" placeholder="Search by student name or student ID..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($searched): ?>
            <?php if ($students_count === 0): ?>
                <div class="no-match">No students found matching "<?php echo htmlspecialchars($search); ?>".</div>
            <?php else: ?>
                <?php while ($s = $students_found->fetch_assoc()): ?>
                    <div class="student-result">
                        <div class="student-result-header">
                            <div class="student-info-line">
                                <div class="student-avatar"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></div>
                                <div>
                                    <div class="student-name-lg"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                        <span class="student-id-tag"><?php echo htmlspecialchars($s['student_id']); ?></span>
                                        <span class="stage-badge">Stage <?php echo intval($s['stage']); ?></span>
                                        <span class="class-badge">Class <?php echo htmlspecialchars($s['class']); ?></span>
                                        <?php if (($s['stage'] == 4 || $s['stage'] == 5) && $s['track']): ?>
                                            <span class="track-badge track-<?php echo strtolower($s['track']); ?>"><?php echo htmlspecialchars($s['track']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <a href="student_profile.php?id=<?php echo $s['id']; ?>" class="view-profile-btn">View Full Profile</a>
                        </div>

                        <?php
                        $cars_stmt = $conn->prepare("SELECT * FROM cars WHERE student_id = ?");
                        $cars_stmt->bind_param("i", $s['id']);
                        $cars_stmt->execute();
                        $cars_res = $cars_stmt->get_result();
                        ?>

                        <?php if ($cars_res->num_rows === 0): ?>
                            <p style="color: #94a3b8; font-size: 13.5px;">No cars registered.</p>
                        <?php else: ?>
                            <div class="car-mini-list">
                                <?php while ($car = $cars_res->fetch_assoc()): 
                                    $blocked = isPlateBlacklisted($conn, $car['plate_number']);
                                ?>
                                    <div class="car-mini <?php echo $blocked ? 'blacklisted' : ''; ?>">
                                        <div>
                                            <span class="plate-tag"><?php echo htmlspecialchars($car['plate_number']); ?></span>
                                            <span class="car-details">
                                                <?php echo htmlspecialchars($car['car_model'] ?: '—'); ?>
                                                <?php if ($car['color']): ?> • <?php echo htmlspecialchars($car['color']); ?><?php endif; ?>
                                            </span>
                                            <?php if ($blocked): ?>
                                                <span class="badge-blocked-sm">BLACKLISTED — <?php echo htmlspecialchars($blocked['reason']); ?></span>
                                                <?php if ($blocked['expires_at']): ?>
                                                    <div class="expires-info">Expires: <?php echo date('M d, Y H:i', strtotime($blocked['expires_at'])); ?></div>
                                                <?php else: ?>
                                                    <div class="expires-info">Permanent</div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($blocked): ?>
                                                <?php if ($is_admin): ?>
                                                    <a href="?remove=<?php
                                                        $r = $conn->query("SELECT id FROM blacklist WHERE plate_number = '" . $conn->real_escape_string($car['plate_number']) . "' AND (expires_at IS NULL OR expires_at > NOW())");
                                                        echo $r->fetch_assoc()['id'];
                                                    ?>" class="btn-unblock" onclick="return confirm('Remove from blacklist?')">Unblock</a>
                                                <?php else: ?>
                                                    <span class="badge-locked">Admin only</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn-blacklist" onclick="openBlacklistModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['plate_number'], ENT_QUOTES); ?>')">Blacklist</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        <?php endif; ?>

        <div class="blacklist-card">
            <h3>Currently Blacklisted Plates (<?php echo $list->num_rows; ?>)</h3>
            <?php if ($list->num_rows === 0): ?>
                <div class="empty">No blacklisted plates yet.</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Plate</th>
                        <th>Student</th>
                        <th>Reason</th>
                        <th>Duration</th>
                        <th>Expires</th>
                        <th>Added By</th>
                        <?php if ($is_admin): ?><th></th><?php endif; ?>
                    </tr>
                    <?php while ($row = $list->fetch_assoc()): ?>
                        <tr>
                            <td><span class="plate"><?php echo htmlspecialchars($row['plate_number']); ?></span></td>
                            <td>
                                <?php if ($row['full_name']): ?>
                                    <div class="name-cell"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <div class="meta-cell">
                                        <?php echo htmlspecialchars($row['student_id']); ?>
                                        • Stage <?php echo intval($row['stage']); ?>
                                        • Class <?php echo htmlspecialchars($row['class']); ?>
                                        <?php if (($row['stage'] == 4 || $row['stage'] == 5) && $row['track']): ?>
                                            • <?php echo htmlspecialchars($row['track']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="unknown">Not registered</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                            <td>
                                <span class="duration-badge <?php echo ($row['duration_label'] == 'Permanent' || !$row['duration_label']) ? 'perm' : ''; ?>">
                                    <?php echo htmlspecialchars($row['duration_label'] ?: 'Permanent'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['expires_at']): ?>
                                    <?php 
                                    $expires = strtotime($row['expires_at']);
                                    $soon = ($expires - time()) < 3600;
                                    ?>
                                    <div class="expires-info <?php echo $soon ? 'soon' : ''; ?>">
                                        <?php echo date('M d, Y H:i', $expires); ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #64748b; font-size: 13px;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="meta-cell"><?php echo htmlspecialchars($row['added_by_username'] ?: 'system'); ?></span></td>
                            <?php if ($is_admin): ?>
                                <td><a href="?remove=<?php echo $row['id']; ?>" class="btn-remove" onclick="return confirm('Remove this plate?')">Remove</a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal-overlay" id="blacklistModal">
        <div class="modal">
            <h3>Blacklist Vehicle</h3>
            <div class="plate-preview" id="modal-plate"></div>
            <form method="POST">
                <input type="hidden" name="blacklist_car" value="1">
                <input type="hidden" name="car_id" id="modal-car-id">
                <label>Reason *</label>
                <input type="text" name="reason" placeholder="e.g. Suspicious behavior" required autofocus>
                <label>Duration</label>
                <select name="duration" required>
                    <option value="1 Hour">1 Hour</option>
                    <option value="6 Hours">6 Hours</option>
                    <option value="1 Day" selected>1 Day</option>
                    <option value="3 Days">3 Days</option>
                    <option value="1 Week">1 Week</option>
                    <option value="2 Weeks">2 Weeks</option>
                    <option value="1 Month">1 Month</option>
                    <option value="3 Months">3 Months</option>
                    <option value="1 Year">1 Year</option>
                    <option value="Permanent">Permanent (until removed)</option>
                </select>
                <div class="modal-actions">
                    <button type="button" class="modal-btn-cancel" onclick="closeBlacklistModal()">Cancel</button>
                    <button type="submit" class="modal-btn-confirm">Blacklist</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openBlacklistModal(carId, plate) {
        document.getElementById('modal-car-id').value = carId;
        document.getElementById('modal-plate').textContent = plate;
        document.getElementById('blacklistModal').classList.add('active');
    }
    function closeBlacklistModal() {
        document.getElementById('blacklistModal').classList.remove('active');
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>