<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');

$search = "";
$results = null;
$searched = false;

if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $searched = true;

    $stmt = $conn->prepare("
        SELECT c.id AS car_id, c.plate_number, c.car_model, c.color, c.car_photo,
               s.full_name, s.student_id, s.department, s.phone, s.photo
        FROM cars c
        JOIN students s ON c.student_id = s.id
        WHERE c.plate_number LIKE ? OR s.full_name LIKE ? OR s.student_id LIKE ?
    ");
    $like = "%" . $search . "%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $results = $stmt->get_result();
}

function isBlacklisted($conn, $plate) {
    $stmt = $conn->prepare("SELECT reason FROM blacklist WHERE plate_number = ?");
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
    <title>Search - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .search-card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .search-form { display: flex; gap: 12px; }
        .search-form input { flex: 1; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 16px; font-family: inherit; }
        .search-form input:focus { outline: none; border-color: #0f172a; }
        .search-form button { background: #0f172a; color: #fff; padding: 14px 32px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .search-tips { margin-top: 14px; font-size: 12.5px; color: #64748b; }
        .search-tips strong { color: #0f172a; }
        .result-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 30px; margin-bottom: 20px; }
        .result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 12px; }
        .plate-badge { font-size: 24px; font-weight: 700; letter-spacing: 2px; background: #0f172a; color: #fff; padding: 10px 20px; border-radius: 8px; font-family: monospace; }
        .status-ok { display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #166534; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .status-blocked { display: inline-flex; align-items: center; gap: 6px; background: #fee2e2; color: #991b1b; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .btn-log { background: #0f172a; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-log:hover { background: #1e293b; }
        .info-grid { display: grid; grid-template-columns: 200px 1fr; gap: 24px; }
        .student-photo { width: 200px; height: 200px; border-radius: 12px; background: #f1f5f9; object-fit: cover; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 60px; }
        .info-row { margin-bottom: 16px; }
        .info-row label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .info-row .value { font-size: 16px; color: #0f172a; font-weight: 500; margin-top: 4px; }
        .empty-state, .no-result { text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
        .empty-state h3 { color: #64748b; font-weight: 500; }
        .no-result h3 { color: #dc2626; }
        .empty-state p, .no-result p { color: #94a3b8; margin-top: 8px; font-size: 14px; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span><?php echo $is_admin ? 'Computer Sciences' : 'Guard Panel'; ?></span></div>
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
            <a href="search_plate.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="view_logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist<?php echo $is_guard ? ' (View)' : ''; ?></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Search</h1>
        <p class="page-sub">Search by plate number, student name, or student ID</p>
        <div class="search-card">
            <form method="GET" class="search-form">
                <input type="text" name="q" placeholder="Plate number, name, or student ID..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
                <button type="submit">Search</button>
            </form>
            <p class="search-tips"><strong>Examples:</strong> 12345 ABC · Ahmed · STU001</p>
        </div>
        <?php if (!$searched): ?>
            <div class="empty-state"><h3>Start typing above</h3><p>Search by plate number, student name, or student ID</p></div>
        <?php elseif ($results->num_rows === 0): ?>
            <div class="no-result"><h3>No match found</h3><p>"<?php echo htmlspecialchars($search); ?>" is not in the system.</p></div>
        <?php else: ?>
            <?php while ($row = $results->fetch_assoc()): 
                $blocked = isBlacklisted($conn, $row['plate_number']);
            ?>
                <div class="result-card">
                    <div class="result-header">
                        <div class="plate-badge"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                        <?php if ($blocked): ?>
                            <div class="status-blocked">BLACKLISTED — <?php echo htmlspecialchars($blocked); ?></div>
                        <?php else: ?>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div class="status-ok">Verified — Allow Entry</div>
                                <a href="log_entry.php?car_id=<?php echo $row['car_id']; ?>" class="btn-log">Log Entry</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="info-grid">
                        <div>
                            <?php if ($row['photo'] && file_exists($row['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($row['photo']); ?>" class="student-photo">
                            <?php else: ?>
                                <div class="student-photo">PHOTO</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="info-row"><label>Student Name</label><div class="value"><?php echo htmlspecialchars($row['full_name']); ?></div></div>
                            <div class="info-row"><label>Student ID</label><div class="value"><?php echo htmlspecialchars($row['student_id']); ?></div></div>
                            <div class="info-row"><label>Department</label><div class="value"><?php echo htmlspecialchars($row['department'] ?: '—'); ?></div></div>
                            <div class="info-row"><label>Phone</label><div class="value"><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></div></div>
                            <div class="info-row"><label>Car Details</label><div class="value"><?php echo htmlspecialchars($row['car_model'] ?: '—'); ?><?php if ($row['color']): ?> • <?php echo htmlspecialchars($row['color']); ?><?php endif; ?></div></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </main>
</body>
</html>