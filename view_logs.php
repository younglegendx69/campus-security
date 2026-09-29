<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');

$logs = $conn->query("
    SELECT l.id, l.entry_time, l.exit_time, l.status,
           c.plate_number, c.car_model,
           s.full_name, s.student_id,
           u.username AS guard_name
    FROM logs l
    JOIN cars c ON l.car_id = c.id
    JOIN students s ON c.student_id = s.id
    JOIN users u ON l.guard_id = u.id
    ORDER BY l.entry_time DESC
    LIMIT 100
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Entry Logs - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.css">
<link rel="stylesheet" href="theme.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .sidebar nav { padding: 16px 12px; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .badge-in { background: #dcfce7; color: #166534; }
        .badge-out { background: #f1f5f9; color: #64748b; }
        .plate { font-family: monospace; background: #0f172a; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; letter-spacing: 1px; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
    </style>
</head>
<body>
<script src="theme.js"></script>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span><?php echo $is_admin ? 'Computer Sciences' : 'Guard Panel'; ?></span></div>
        </div>
        <div style="padding: 12px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Theme</span>
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            </button>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="<?php echo $is_admin ? 'dashboard.php' : 'guard_dashboard.php'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <?php if ($is_admin): ?>
                <div class="nav-label">Management</div>
                <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
                <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <?php endif; ?>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search Plate</a>
            <a href="view_logs.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist<?php echo $is_guard ? ' (View)' : ''; ?></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Entry Logs</h1>
        <?php if ($logs->num_rows === 0): ?>
            <div class="empty">No entries yet. Search for a plate and click "Log Entry".</div>
        <?php else: ?>
            <div class="table-card">
                <table>
                    <thead><tr><th>Plate</th><th>Student</th><th>Entry</th><th>Exit</th><th>Status</th><th>Guard</th></tr></thead>
                    <tbody>
                        <?php while ($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><span class="plate"><?php echo htmlspecialchars($row['plate_number']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br><span style="color:#64748b;font-size:12px;"><?php echo htmlspecialchars($row['student_id']); ?></span></td>
                                <td><?php echo date('M d, Y H:i', strtotime($row['entry_time'])); ?></td>
                                <td><?php echo $row['exit_time'] ? date('M d, Y H:i', strtotime($row['exit_time'])) : '—'; ?></td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['guard_name']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>