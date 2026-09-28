<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');
$success = "";
$error = "";

// Add to blacklist — admin only
if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "add") {
    $plate = strtoupper(trim($_POST['plate_number']));
    $reason = trim($_POST['reason']);

    if (empty($plate)) {
        $error = "Plate number is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO blacklist (plate_number, reason) VALUES (?, ?)");
        $stmt->bind_param("ss", $plate, $reason);
        if ($stmt->execute()) {
            $success = "Plate added to blacklist.";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Remove from blacklist — admin only
if ($is_admin && isset($_GET['remove'])) {
    $id = intval($_GET['remove']);
    $conn->query("DELETE FROM blacklist WHERE id = $id");
    header("Location: blacklist.php");
    exit;
}

$list = $conn->query("SELECT * FROM blacklist ORDER BY date_added DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blacklist - Sardam Institute</title>
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: <?php echo $is_admin ? '400px 1fr' : '1fr'; ?>; gap: 24px; }
        .card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .card h3 { font-size: 15px; font-weight: 600; margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input, textarea { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; margin-bottom: 16px; }
        input:focus, textarea:focus { outline: none; border-color: #0f172a; }
        button { background: #dc2626; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; width: 100%; }
        button:hover { background: #b91c1c; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .plate { font-family: monospace; background: #dc2626; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; letter-spacing: 1px; }
        .btn-remove { color: #dc2626; text-decoration: none; font-size: 13px; font-weight: 500; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }
        .guard-note { background: #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; border: 1px solid #fde68a; }
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
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search Plate</a>
            <a href="view_logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist<?php echo $is_guard ? ' (View)' : ''; ?></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Blacklist Management</h1>

        <?php if ($is_guard): ?>
            <div class="guard-note">View-only mode. Contact an administrator to add or remove blacklisted plates.</div>
        <?php endif; ?>

        <div class="grid">
            <?php if ($is_admin): ?>
                <div class="card">
                    <h3>Add Plate to Blacklist</h3>
                    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" placeholder="e.g. 99999 XYZ" required>
                        <label>Reason</label>
                        <textarea name="reason" placeholder="Why is this plate banned?" rows="3"></textarea>
                        <button type="submit">Add to Blacklist</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>Blacklisted Plates (<?php echo $list->num_rows; ?>)</h3>
                <?php if ($list->num_rows === 0): ?>
                    <div class="empty">No blacklisted plates yet.</div>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Plate</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <?php if ($is_admin): ?><th></th><?php endif; ?>
                        </tr>
                        <?php while ($row = $list->fetch_assoc()): ?>
                            <tr>
                                <td><span class="plate"><?php echo htmlspecialchars($row['plate_number']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['reason'] ?: '—'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['date_added'])); ?></td>
                                <?php if ($is_admin): ?>
                                    <td><a href="?remove=<?php echo $row['id']; ?>" class="btn-remove" onclick="return confirm('Remove this plate?')">Remove</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>