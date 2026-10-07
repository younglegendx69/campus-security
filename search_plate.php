<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');

$search = "";
$where = "";

if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $like = "%$search%";
    $where = "WHERE s.full_name LIKE ? OR s.student_id LIKE ? OR c.plate_number LIKE ?";
}

if ($where) {
    $stmt = $conn->prepare("
        SELECT s.id, s.student_id, s.full_name, s.stage, s.class, s.track, s.phone, s.photo,
               GROUP_CONCAT(c.plate_number SEPARATOR ', ') AS plates,
               COUNT(c.id) AS car_count
        FROM students s
        LEFT JOIN cars c ON c.student_id = s.id
        $where
        GROUP BY s.id
        ORDER BY s.full_name ASC
        LIMIT 200
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query("
        SELECT s.id, s.student_id, s.full_name, s.stage, s.class, s.track, s.phone, s.photo,
               GROUP_CONCAT(c.plate_number SEPARATOR ', ') AS plates,
               COUNT(c.id) AS car_count
        FROM students s
        LEFT JOIN cars c ON c.student_id = s.id
        GROUP BY s.id
        ORDER BY s.full_name ASC
        LIMIT 200
    ");
}

$total = $students->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Search - Sardam Institute</title>
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
        .search-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .search-form { display: flex; gap: 12px; }
        .search-form input { flex: 1; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; font-family: inherit; }
        .search-form input:focus { outline: none; border-color: #0f172a; }
        .search-form button { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .search-form .btn-clear { background: #f1f5f9; color: #475569; padding: 14px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .result-info { font-size: 13.5px; color: #64748b; margin-bottom: 14px; }
        .result-info strong { color: #0f172a; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; vertical-align: middle; margin-right: 10px; }
        .student-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; }
        .student-link:hover .student-name { color: #2563eb; text-decoration: underline; }
        .student-name { font-weight: 600; color: #0f172a; }
        .student-id { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .stage-badge { display: inline-block; background: #f1f5f9; color: #0f172a; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .class-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-programming { background: #dbeafe; color: #1e40af; }
        .track-networking { background: #dcfce7; color: #166534; }
        .track-network { background: #dcfce7; color: #166534; }
        .plate-tag { display: inline-block; font-family: monospace; background: #0f172a; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11.5px; letter-spacing: 1px; margin-right: 4px; margin-bottom: 3px; }
        .plate-blocked { background: #dc2626; }
        .no-cars { color: #94a3b8; font-size: 12.5px; font-style: italic; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
        .empty h3 { font-size: 16px; font-weight: 500; margin-bottom: 6px; }
        .empty p { font-size: 13.5px; color: #94a3b8; }
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
            <a href="search_plate.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
            <?php if ($is_admin): ?>
                <div class="nav-label">Account</div>
                <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Manage Users</a>
                <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Change Password</a>
            <?php endif; ?>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Search</h1>
        <p class="page-sub">Search by plate number, student name, or student ID</p>

        <div class="search-card">
            <form method="GET" class="search-form">
                <input type="text" name="q" placeholder="Plate number, name, or student ID..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button type="submit">Search</button>
                <?php if ($search): ?>
                    <a href="search_plate.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <p class="result-info">
            Showing <strong><?php echo $total; ?></strong> student<?php echo $total != 1 ? 's' : ''; ?>
            <?php if ($search): ?>
                matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
            <?php else: ?>
                (all registered students)
            <?php endif; ?>
        </p>

        <?php if ($total === 0): ?>
            <div class="empty">
                <h3>No students found</h3>
                <p><?php echo $search ? 'Try a different search term.' : 'No students registered yet.'; ?></p>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Stage</th>
                            <th>Class</th>
                            <th>Field</th>
                            <th>Plate Numbers</th>
                            <th>Cars</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="student_profile.php?id=<?php echo $s['id']; ?>" class="student-link">
                                        <span class="avatar-sm"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></span>
                                        <span class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                    </a>
                                </td>
                                <td><span class="student-id"><?php echo htmlspecialchars($s['student_id']); ?></span></td>
                                <td><span class="stage-badge">Stage <?php echo intval($s['stage']); ?></span></td>
                                <td><span class="class-badge">Class <?php echo htmlspecialchars($s['class']); ?></span></td>
                                <td>
                                    <?php if (($s['stage'] == 4 || $s['stage'] == 5) && $s['track']): ?>
                                        <span class="track-badge track-<?php echo strtolower($s['track']); ?>"><?php echo htmlspecialchars($s['track']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['plates']): 
                                        $plates = explode(', ', $s['plates']);
                                        foreach ($plates as $plate):
                                            $stmt = $conn->prepare("SELECT reason FROM blacklist WHERE plate_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
                                            $stmt->bind_param("s", $plate);
                                            $stmt->execute();
                                            $bl = $stmt->get_result()->num_rows > 0;
                                    ?>
                                        <span class="plate-tag <?php echo $bl ? 'plate-blocked' : ''; ?>"><?php echo htmlspecialchars($plate); ?></span>
                                    <?php endforeach; else: ?>
                                        <span class="no-cars">No cars</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['car_count'] > 0): ?>
                                        <strong><?php echo $s['car_count']; ?></strong>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>