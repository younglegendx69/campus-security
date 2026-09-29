<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');

if (!isset($_GET['id'])) {
    header("Location: view_students.php");
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header("Location: view_students.php");
    exit;
}

$stmt2 = $conn->prepare("SELECT * FROM cars WHERE student_id = ?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$cars = $stmt2->get_result();
$cars_array = [];
while ($c = $cars->fetch_assoc()) $cars_array[] = $c;
$car_count = count($cars_array);

function isPlateBlacklisted($conn, $plate) {
    $stmt = $conn->prepare("SELECT reason FROM blacklist WHERE plate_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->bind_param("s", $plate);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r->num_rows > 0) return $r->fetch_assoc()['reason'];
    return false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($student['full_name']); ?> - Sardam Institute</title>
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
        .back-link { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 13px; margin-bottom: 20px; }
        .profile-header { background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; padding: 32px; border-radius: 16px; display: flex; align-items: center; gap: 24px; margin-bottom: 24px; }
        .profile-photo { width: 100px; height: 100px; border-radius: 50%; background: #f1f5f9; object-fit: cover; flex-shrink: 0; border: 4px solid rgba(255,255,255,0.2); }
        .profile-photo-placeholder { width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: 700; border: 4px solid rgba(255,255,255,0.2); flex-shrink: 0; }
        .profile-header h1 { font-size: 26px; margin-bottom: 6px; }
        .profile-header .id-badge { display: inline-block; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 6px; font-size: 13px; font-family: monospace; }
        .profile-header .dept { color: #cbd5e1; font-size: 14px; margin-top: 8px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .card h3 { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .info-row { display: flex; padding: 12px 0; border-bottom: 1px solid #f8fafc; }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #64748b; font-size: 13px; width: 140px; flex-shrink: 0; }
        .info-row .value { font-weight: 500; font-size: 14px; }
        .stat-box { background: #f8fafc; padding: 18px; border-radius: 10px; text-align: center; }
        .stat-box .num { font-size: 32px; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-box .lbl { font-size: 12px; color: #64748b; margin-top: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .car-card { padding: 16px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px; }
        .car-card.blacklisted { background: #fef2f2; border-color: #fecaca; }
        .car-card .plate { display: inline-block; font-family: monospace; background: #0f172a; color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 14px; letter-spacing: 1px; margin-bottom: 10px; }
        .car-card.blacklisted .plate { background: #dc2626; }
        .car-card .model { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .car-card .color { font-size: 13px; color: #64748b; }
        .empty-cars { text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; }
        .btn { background: #0f172a; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500; display: inline-block; margin-right: 8px; }
        .btn-edit { background: #2563eb; }
        .btn-danger { background: #dc2626; }
        .actions { margin-top: 20px; }
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
                <a href="view_students.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
                <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
            <?php endif; ?>

            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
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
        <a href="view_students.php" class="back-link">&larr; Back to All Students</a>

        <div class="profile-header">
            <?php if ($student['photo'] && file_exists($student['photo'])): ?>
                <img src="<?php echo htmlspecialchars($student['photo']); ?>" class="profile-photo">
            <?php else: ?>
                <div class="profile-photo-placeholder"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
            <?php endif; ?>
            <div>
                <h1><?php echo htmlspecialchars($student['full_name']); ?></h1>
                <span class="id-badge"><?php echo htmlspecialchars($student['student_id']); ?></span>
                <div class="dept"><?php echo htmlspecialchars($student['department'] ?: 'No department'); ?></div>
            </div>
        </div>

        <div class="grid">
            <div>
                <div class="card">
                    <h3>Contact Information</h3>
                    <div class="info-row"><div class="label">Student ID</div><div class="value"><?php echo htmlspecialchars($student['student_id']); ?></div></div>
                    <div class="info-row"><div class="label">Full Name</div><div class="value"><?php echo htmlspecialchars($student['full_name']); ?></div></div>
                    <div class="info-row"><div class="label">Department</div><div class="value"><?php echo htmlspecialchars($student['department'] ?: '—'); ?></div></div>
                    <div class="info-row"><div class="label">Phone</div><div class="value"><?php echo htmlspecialchars($student['phone'] ?: '—'); ?></div></div>
                    <div class="info-row"><div class="label">Registered</div><div class="value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></div></div>
                </div>

                <div class="card">
                    <h3>Registered Vehicles (<?php echo $car_count; ?>)</h3>
                    <?php if ($car_count === 0): ?>
                        <div class="empty-cars">No vehicles registered.</div>
                    <?php else: ?>
                        <?php foreach ($cars_array as $car): 
                            $blocked = isPlateBlacklisted($conn, $car['plate_number']);
                        ?>
                            <div class="car-card <?php echo $blocked ? 'blacklisted' : ''; ?>">
                                <div class="plate"><?php echo htmlspecialchars($car['plate_number']); ?></div>
                                <div class="model"><?php echo htmlspecialchars($car['car_model'] ?: 'Unknown model'); ?></div>
                                <div class="color"><?php echo htmlspecialchars($car['color'] ?: 'Unknown color'); ?></div>
                                <?php if ($blocked): ?>
                                    <div style="margin-top: 8px; font-size: 12px; color: #dc2626; font-weight: 600;">BLACKLISTED — <?php echo htmlspecialchars($blocked); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <h3>Statistics</h3>
                    <div class="stat-box">
                        <div class="num"><?php echo $car_count; ?></div>
                        <div class="lbl">Vehicles</div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <div class="card">
                    <h3>Actions</h3>
                    <div class="actions">
                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this student?')">Delete</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>