<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$search = "";
$where = "";
if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $where = "WHERE s.full_name LIKE '%$search%' OR s.student_id LIKE '%$search%'";
}

$students = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM cars WHERE student_id = s.id) AS car_count
    FROM students s
    $where
    ORDER BY s.id DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Students - Sardam Institute</title>
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .toolbar { display: flex; gap: 12px; margin-bottom: 20px; }
        .toolbar form { display: flex; flex: 1; gap: 12px; }
        .toolbar input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-family: inherit; }
        .toolbar input:focus { outline: none; border-color: #0f172a; }
        .toolbar button { background: #0f172a; color: #fff; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-add { background: #2563eb; color: #fff; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: 500; white-space: nowrap; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; vertical-align: middle; margin-right: 10px; flex-shrink: 0; }
        .student-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; }
        .student-link:hover strong { color: #2563eb; text-decoration: underline; }
        .student-id { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .car-badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .no-cars { color: #94a3b8; font-size: 12px; }
        .actions { display: flex; gap: 10px; }
        .btn-edit { color: #2563eb; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 10px; border: 1px solid #dbeafe; border-radius: 6px; background: #eff6ff; }
        .btn-del { color: #dc2626; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 10px; border: 1px solid #fee2e2; border-radius: 6px; background: #fef2f2; }
        .btn-view { color: #059669; text-decoration: none; font-size: 13px; font-weight: 500; padding: 5px 10px; border: 1px solid #d1fae5; border-radius: 6px; background: #ecfdf5; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
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
            <div class="name">Sardam Institute<span>Computer Sciences</span></div>
        </div>
        <div style="padding: 12px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Theme</span>
            <button onclick="toggleTheme()" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <div class="nav-label">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Bulk Import</a>
            <a href="view_students.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
            <div class="nav-label">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Manage Users</a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Change Password</a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">All Students</h1>
        <div class="toolbar">
            <form method="GET">
                <input type="text" name="q" placeholder="Search by name or student ID..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
            <a href="add_student.php" class="btn-add">+ Add Student</a>
        </div>

        <?php if ($students->num_rows === 0): ?>
            <div class="empty">No students found.</div>
        <?php else: ?>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Department</th>
                            <th>Cars</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="student_profile.php?id=<?php echo $s['id']; ?>" class="student-link">
                                        <span class="avatar-sm"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></span>
                                        <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                    </a>
                                </td>
                                <td><span class="student-id"><?php echo htmlspecialchars($s['student_id']); ?></span></td>
                                <td><?php echo htmlspecialchars($s['department'] ?: '—'); ?></td>
                                <td>
                                    <?php if ($s['car_count'] > 0): ?>
                                        <span class="car-badge"><?php echo $s['car_count']; ?> car<?php echo $s['car_count'] > 1 ? 's' : ''; ?></span>
                                    <?php else: ?>
                                        <span class="no-cars">No cars</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="student_profile.php?id=<?php echo $s['id']; ?>" class="btn-view">View</a>
                                        <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="btn-edit">Edit</a>
                                        <a href="delete_student.php?id=<?php echo $s['id']; ?>" class="btn-del" onclick="return confirm('Delete <?php echo htmlspecialchars($s['full_name']); ?>?')">Delete</a>
                                    </div>
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