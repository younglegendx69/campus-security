<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'] ?? 'guard';
    if (empty($username) || empty($password)) $error = "Username and password are required.";
    elseif (!in_array($role, ['admin', 'guard'])) $error = "Invalid role.";
    else {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $error = "Username already exists.";
        else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $username, $hashed, $role);
            if ($ins->execute()) $success = "User '$username' created as $role.";
            else $error = "Error creating user.";
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id == $_SESSION['user_id']) $error = "You cannot delete your own account.";
    else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $success = "User deleted.";
    }
}

if (isset($_POST['change_role'])) {
    $id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'] ?? 'guard';
    if (in_array($new_role, ['admin', 'guard']) && $id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $id);
        $stmt->execute();
        $success = "Role updated.";
    } elseif ($id == $_SESSION['user_id']) $error = "You cannot change your own role.";
}

$users = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css?v=99">
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
        .lang-btn { background: #f1f5f9; border: 1px solid #e2e8f0; height: 28px; padding: 0 10px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 11.5px; color: #475569; font-family: inherit; }
        .lang-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; align-items: start; }
        .add-card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .add-card h3 { font-size: 15px; font-weight: 700; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=password], select { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fff; }
        input:focus, select:focus { outline: none; border-color: #0f172a; }
        .btn { background: #0f172a; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #1e293b; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .avatar-sm { width: 34px; height: 34px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; margin-right: 10px; vertical-align: middle; }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .role-admin { background: #fef3c7; color: #92400e; }
        .role-guard { background: #dbeafe; color: #1e40af; }
        .you-badge { display: inline-block; background: #dcfce7; color: #166534; font-size: 10.5px; padding: 2px 8px; border-radius: 4px; font-weight: 700; margin-left: 8px; text-transform: uppercase; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .role-select { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12.5px; font-family: inherit; background: #fff; }
        .btn-delete { color: #dc2626; text-decoration: none; font-size: 12.5px; font-weight: 600; padding: 5px 10px; border: 1px solid #fee2e2; border-radius: 6px; background: #fef2f2; }
        .btn-delete:hover { background: #fee2e2; }
        .disabled-note { color: #94a3b8; font-size: 12px; font-style: italic; }
        .date-cell { font-size: 12.5px; color: #64748b; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

        /* ============================================
           DARK MODE — FULL FIX
           ============================================ */
        html.dark-mode { background: #0f172a !important; }
        html.dark-mode body { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .sidebar { background: #1e293b !important; border-right-color: #334155 !important; }
        html.dark-mode .sidebar-header { border-bottom-color: #334155 !important; }
        html.dark-mode .sidebar-header .name { color: #f1f5f9 !important; }
        html.dark-mode .sidebar-header .name span { color: #94a3b8 !important; }
        html.dark-mode .sidebar nav a { color: #cbd5e1 !important; }
        html.dark-mode .sidebar nav a:hover { background: #334155 !important; color: #fff !important; }
        html.dark-mode .sidebar nav a.active { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .nav-label { color: #64748b !important; }
        html.dark-mode .main { background: #0f172a !important; }
        html.dark-mode .page-title { color: #f1f5f9 !important; }
        html.dark-mode .page-sub { color: #94a3b8 !important; }
        html.dark-mode .add-card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .add-card h3 { color: #f1f5f9 !important; }
        html.dark-mode .table-card { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode label { color: #cbd5e1 !important; }
        html.dark-mode input[type=text],
        html.dark-mode input[type=password],
        html.dark-mode select { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode input::placeholder { color: #64748b !important; }
        html.dark-mode select option { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode table { background: #1e293b !important; }
        html.dark-mode th { background: #0f172a !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        html.dark-mode td { color: #f1f5f9 !important; border-bottom-color: #334155 !important; }
        html.dark-mode tr:hover td { background: #334155 !important; }
        html.dark-mode .date-cell { color: #94a3b8 !important; }
        html.dark-mode .disabled-note { color: #94a3b8 !important; }
        html.dark-mode .avatar-sm { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .role-select { background: #0f172a !important; color: #f1f5f9 !important; border-color: #475569 !important; }
        html.dark-mode .role-select option { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .btn { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .btn:hover { background: #2563eb !important; }
        html.dark-mode .btn-delete { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
        html.dark-mode .role-admin { background: #78350f !important; color: #fef3c7 !important; }
        html.dark-mode .role-guard { background: #1e40af !important; color: #dbeafe !important; }
        html.dark-mode .you-badge { background: #166534 !important; color: #dcfce7 !important; }
        html.dark-mode .alert-success { background: #14532d !important; color: #bbf7d0 !important; border-color: #166534 !important; }
        html.dark-mode .alert-error { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
        html.dark-mode .lang-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .lang-btn:hover { background: #475569 !important; }
        html.dark-mode .lang-btn.active { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; }
        html.dark-mode button[onclick*="toggleTheme"] { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
    </style>
    <script>
    (function() {
        try {
            var saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') document.documentElement.classList.add('dark-mode');
            var lang = localStorage.getItem('lang') || 'en';
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        } catch(e) {}
    })();
    </script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span data-en="Computer Sciences" data-ku="زانستەکانی کۆمپیوتەر" data-ar="علوم الحاسوب">Computer Sciences</span></div>
        </div>
        <div style="padding: 10px 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase;" data-en="Lang" data-ku="زمان" data-ar="لغة">Lang</span>
            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                <button onclick="setLanguage('en')" class="lang-btn">English</button>
                <button onclick="setLanguage('ku')" class="lang-btn">کوردی</button>
                <button onclick="setLanguage('ar')" class="lang-btn">عربي</button>
                <button onclick="toggleTheme()" title="Theme" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
        <nav>
            <div class="nav-label" data-en="Main" data-ku="سەرەکی" data-ar="الرئيسية">Main</div>
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</h1>
        <p class="page-sub" data-en="Add, change roles, or remove admin and guard accounts" data-ku="زیادکردن، گۆڕینی ڕۆڵ، یان سڕینەوەی هەژماری ئەدمین و پاسەوان" data-ar="إضافة أو تغيير الأدوار أو إزالة حسابات المدير والحراس">Add, change roles, or remove admin and guard accounts</p>

        <?php if ($success) echo "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>

        <div class="grid">
            <div class="add-card">
                <h3 data-en="Add New User" data-ku="زیادکردنی بەکارهێنەری نوێ" data-ar="إضافة مستخدم جديد">Add New User</h3>
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="form-group">
                        <label data-en="Username" data-ku="ناوی بەکارهێنەر" data-ar="اسم المستخدم">Username</label>
                        <input type="text" name="username" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label data-en="Password" data-ku="وشەی نهێنی" data-ar="كلمة المرور">Password</label>
                        <input type="password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label data-en="Role" data-ku="ڕۆڵ" data-ar="الدور">Role</label>
                        <select name="role" required>
                            <option value="guard" data-en="Guard" data-ku="پاسەوان" data-ar="حارس">Guard</option>
                            <option value="admin" data-en="Admin" data-ku="ئەدمین" data-ar="مدير">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn" data-en="+ Create User" data-ku="+ دروستکردنی بەکارهێنەر" data-ar="+ إنشاء مستخدم">+ Create User</button>
                </form>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th data-en="User" data-ku="بەکارهێنەر" data-ar="المستخدم">User</th>
                            <th data-en="Role" data-ku="ڕۆڵ" data-ar="الدور">Role</th>
                            <th data-en="Created" data-ku="دروستکراوە" data-ar="تم الإنشاء">Created</th>
                            <th data-en="Actions" data-ku="ڕێوشوێنەکان" data-ar="الإجراءات">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = $users->fetch_assoc()): ?>
                            <?php $is_self = ($u['id'] == $_SESSION['user_id']); ?>
                            <tr>
                                <td>
                                    <span class="avatar-sm"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></span>
                                    <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                    <?php if ($is_self): ?><span class="you-badge" data-en="You" data-ku="تۆ" data-ar="أنت">You</span><?php endif; ?>
                                </td>
                                <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                <td class="date-cell"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if ($is_self): ?>
                                        <span class="disabled-note" data-en="Cannot modify your own account" data-ku="ناتوانیت هەژماری خۆت بگۆڕیت" data-ar="لا يمكنك تعديل حسابك الخاص">Cannot modify your own account</span>
                                    <?php else: ?>
                                        <div class="actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="change_role" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <select name="new_role" class="role-select" onchange="this.form.submit()">
                                                    <option value="guard" <?php echo $u['role'] == 'guard' ? 'selected' : ''; ?>>Guard</option>
                                                    <option value="admin" <?php echo $u['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </form>
                                            <a href="?delete=<?php echo $u['id']; ?>" class="btn-delete" onclick="return confirm('Delete user <?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>?')" data-en="Delete" data-ku="سڕینەوە" data-ar="حذف">Delete</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="language.js?v=99"></script>
    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>