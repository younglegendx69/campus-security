<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

// Reset a user's password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_pass = trim($_POST['new_password']);

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_pass, $user_id);
        if ($stmt->execute()) {
            $success = "Password updated successfully.";
        } else {
            $error = "Failed to update password.";
        }
    }
}

// Add a new user
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? 'guard';

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!in_array($role, ['admin', 'guard'])) {
        $error = "Invalid role.";
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $ins = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $username, $password, $role);
            if ($ins->execute()) {
                $success = "User '" . htmlspecialchars($username) . "' added as " . htmlspecialchars($role) . ".";
            } else {
                $error = "Failed to add user.";
            }
        }
    }
}

// Delete a user
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $conn->query("DELETE FROM users WHERE id = $del_id");
        header("Location: manage_users.php?deleted=1");
        exit;
    }
}

if (isset($_GET['deleted'])) $success = "User deleted.";

$users = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY role ASC, username ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Sardam Institute</title>
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
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }
        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; }
        .card h3 { font-size: 15px; font-weight: 700; margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input, select { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; margin-bottom: 14px; }
        input:focus, select:focus { outline: none; border-color: #0f172a; }
        .btn { background: #0f172a; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .role-badge { display: inline-block; padding: 3px 10px; border-radius: 5px; font-size: 11.5px; font-weight: 600; }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-guard { background: #dbeafe; color: #1e40af; }
        .username-cell { font-weight: 600; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }
        .btn-reset { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .inline-form { display: flex; gap: 6px; align-items: center; }
        .inline-form input { width: 130px; margin-bottom: 0; padding: 6px 10px; font-size: 12.5px; }
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
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
<a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Promote Students</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
            <div class="nav-label">Account</div>
            <a href="manage_users.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Manage Users</a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Change Password</a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Manage Users</h1>
        <p class="page-sub">Add or remove admins and guards, and reset any password</p>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="grid">
            <div class="card">
                <h3>Add New User</h3>
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="e.g. guard2" required>
                    <label>Password</label>
                    <input type="text" name="password" placeholder="Minimum 6 characters" required minlength="6">
                    <label>Role</label>
                    <select name="role">
                        <option value="guard">Guard</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" class="btn">+ Add User</button>
                </form>
            </div>

            <div class="card">
                <h3>Existing Users (<?php echo $users->num_rows; ?>)</h3>
                <table>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Reset Password</th>
                        <th></th>
                    </tr>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td class="username-cell"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $u['role']; ?>">
                                    <?php echo strtoupper($u['role']); ?>
                                </span>
                            </td>
                            <td style="font-size: 12.5px; color: #64748b;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($u['username']); ?>?')">
                                    <input type="hidden" name="reset_password" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="text" name="new_password" placeholder="New password" required minlength="6">
                                    <button type="submit" class="btn-small btn-reset">Reset</button>
                                </form>
                            </td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?php echo $u['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Delete user <?php echo htmlspecialchars($u['username']); ?>?')">Delete</a>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; color: #94a3b8;">(you)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
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