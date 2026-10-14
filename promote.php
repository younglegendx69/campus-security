<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_default'])) {
    $def = $_POST['default_field'] ?? '';
    if (!in_array($def, ['Programming', 'Network'])) {
        $error = "Choose Programming or Network.";
    } else {
        $stmt = $conn->prepare("UPDATE students SET track = ? WHERE stage = 3 AND (track IS NULL OR track = '')");
        $stmt->bind_param("s", $def);
        $stmt->execute();
        $success = $stmt->affected_rows . " students assigned to " . $def . ".";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_one'])) {
    $sid = intval($_POST['student_id']);
    $field = $_POST['field'] ?? '';
    if (in_array($field, ['Programming', 'Network'])) {
        $stmt = $conn->prepare("UPDATE students SET track = ? WHERE id = ? AND stage = 3 AND (track IS NULL OR track = '')");
        $stmt->bind_param("si", $field, $sid);
        $stmt->execute();
        $success = "Field assigned.";
    }
}

if (isset($_GET['clear'])) {
    $sid = intval($_GET['clear']);
    $conn->query("UPDATE students SET track = NULL WHERE id = $sid AND stage = 3");
    header("Location: promote.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_promote'])) {
    $chk = $conn->query("SELECT COUNT(*) AS c FROM students WHERE stage = 3 AND (track IS NULL OR track = '')");
    $missing = $chk->fetch_assoc()['c'];
    if ($missing > 0) {
        $error = "$missing Stage 3 students still need a field.";
    } else {
        $grad = $conn->query("SELECT id FROM students WHERE stage = 5");
        $graduated = $grad->num_rows;
        $conn->query("DELETE FROM students WHERE stage = 5");
        $promoted = 0;
        for ($from = 4; $from >= 1; $from--) {
            $to = $from + 1;
            $stmt = $conn->prepare("UPDATE students SET stage = ? WHERE stage = ?");
            $stmt->bind_param("ii", $to, $from);
            $stmt->execute();
            $promoted += $stmt->affected_rows;
        }
        $success = "Promotion complete — $promoted promoted, $graduated graduated.";
    }
}

$stages = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$r = $conn->query("SELECT stage, COUNT(*) AS c FROM students GROUP BY stage");
while ($row = $r->fetch_assoc()) { $stages[intval($row['stage'])] = intval($row['c']); }
$total = array_sum($stages);

$q = trim($_GET['q'] ?? '');
$where = "stage = 3 AND (track IS NULL OR track = '')";
if ($q !== '') {
    $like = "%" . $conn->real_escape_string($q) . "%";
    $where .= " AND (full_name LIKE '$like' OR student_id LIKE '$like')";
}
$need_field = $conn->query("SELECT id, full_name, student_id FROM students WHERE $where ORDER BY full_name LIMIT 500");
$c = $conn->query("SELECT COUNT(*) AS c FROM students WHERE stage = 3 AND (track IS NULL OR track = '')");
$need_count = $c->fetch_assoc()['c'];
$already_set = $conn->query("SELECT id, full_name, student_id, track FROM students WHERE stage = 3 AND track IS NOT NULL AND track != '' ORDER BY full_name LIMIT 200");
$assigned_count = $already_set->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Promote Students - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css?v=99">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; }

        /* SIDEBAR (same as other pages) */
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

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* CARDS (same white card as everything else) */
        .card { background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 900px; margin-bottom: 20px; }
        .card h3 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .card .desc { color: #64748b; font-size: 13px; margin-bottom: 18px; }

        /* Preview rows */
        .stage-list { display: grid; gap: 8px; }
        .stage-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .stage-row .num { font-weight: 700; color: #0f172a; }
        .stage-row.highlight { background: #fef3c7; border-color: #fde68a; }

        /* Field pick buttons */
        .pick-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
        .pick-btns label { cursor: pointer; }
        .pick-btns input { display: none; }
        .pick-btns span {
            display: block; text-align: center; padding: 18px;
            border: 2px solid #cbd5e1; border-radius: 10px;
            font-size: 15px; font-weight: 700; background: #fff;
            color: #475569; transition: 0.15s;
        }
        .pick-btns span:hover { border-color: #0f172a; color: #0f172a; }
        .pick-btns input:checked + span { background: #0f172a; color: #fff; border-color: #0f172a; }

        .btn-apply { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; font-family: inherit; }
        .btn-apply:hover { background: #1e293b; }

        /* Toolbar */
        .toolbar { display: flex; gap: 8px; margin-bottom: 14px; }
        .toolbar input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: inherit; font-size: 14px; background: #fff; color: inherit; }
        .toolbar input:focus { outline: none; border-color: #0f172a; }
        .toolbar button { background: #0f172a; color: #fff; padding: 12px 20px; border: none; border-radius: 10px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .toolbar a { padding: 12px 16px; text-decoration: none; color: #475569; font-size: 13.5px; font-weight: 500; border-radius: 10px; background: #f1f5f9; display: inline-flex; align-items: center; }

        /* Student list */
        .stu-list { max-height: 460px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
        .stu-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
        .stu-row:last-child { border-bottom: none; }
        .stu-row:hover { background: #f8fafc; }
        .stu-name { font-size: 13.5px; font-weight: 600; color: #0f172a; }
        .stu-id { font-family: monospace; font-size: 11.5px; color: #64748b; margin-left: 8px; }
        .mini-btns { display: flex; gap: 6px; }
        .mini-btns button { padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1.5px solid #cbd5e1; background: #fff; color: #475569; font-family: inherit; }
        .mini-btns button.prog { border-color: #93c5fd; color: #1e40af; }
        .mini-btns button.prog:hover { background: #dbeafe; border-color: #2563eb; }
        .mini-btns button.net { border-color: #86efac; color: #166534; }
        .mini-btns button.net:hover { background: #dcfce7; border-color: #16a34a; }

        /* Assigned list */
        .assigned-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; }
        .assigned-row:last-child { border-bottom: none; }
        .assigned-list { max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
        .tag { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .tag-prog { background: #dbeafe; color: #1e40af; }
        .tag-net { background: #dcfce7; color: #166534; }
        .clear-link { color: #dc2626; font-size: 12px; text-decoration: none; font-weight: 500; margin-left: 8px; }
        .clear-link:hover { text-decoration: underline; }

        .info-box { background: #e0f2fe; color: #075985; padding: 12px 16px; border-radius: 10px; font-size: 13px; line-height: 1.5; margin-bottom: 16px; border: 1px solid #bae6fd; }
        .warn-box { background: #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 10px; font-size: 13.5px; margin-bottom: 16px; border: 1px solid #fde68a; line-height: 1.6; }

        .btn-promote { background: #dc2626; color: #fff; padding: 16px 32px; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; font-family: inherit; }
        .btn-promote:hover { background: #b91c1c; }
        .btn-promote:disabled { background: #94a3b8; cursor: not-allowed; }

        .empty { padding: 30px; text-align: center; color: #94a3b8; font-size: 13.5px; }

        /* ============================================
           DARK MODE (matches your other pages)
           ============================================ */
        html.dark-mode, html.dark-mode body { background: #0f172a !important; color: #f1f5f9 !important; }
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

        html.dark-mode .card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .card h3 { color: #f1f5f9 !important; }
        html.dark-mode .card .desc { color: #94a3b8 !important; }

        html.dark-mode .stage-row { background: #0f172a !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .stage-row .num { color: #f1f5f9 !important; }
        html.dark-mode .stage-row.highlight { background: #78350f !important; border-color: #92400e !important; color: #fde68a !important; }

        html.dark-mode .pick-btns span { background: #0f172a !important; border-color: #475569 !important; color: #cbd5e1 !important; }
        html.dark-mode .pick-btns span:hover { border-color: #60a5fa !important; }
        html.dark-mode .pick-btns input:checked + span { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; }
        html.dark-mode .btn-apply { background: #3b82f6 !important; }
        html.dark-mode .btn-apply:hover { background: #2563eb !important; }

        html.dark-mode .toolbar input { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .toolbar button { background: #3b82f6 !important; }
        html.dark-mode .toolbar a { background: #334155 !important; color: #e2e8f0 !important; }

        html.dark-mode .stu-list { border-color: #334155 !important; }
        html.dark-mode .stu-row { border-bottom-color: #334155 !important; }
        html.dark-mode .stu-row:hover { background: #334155 !important; }
        html.dark-mode .stu-name { color: #f1f5f9 !important; }
        html.dark-mode .stu-id { color: #94a3b8 !important; }
        html.dark-mode .mini-btns button { background: #0f172a !important; border-color: #475569 !important; color: #cbd5e1 !important; }
        html.dark-mode .mini-btns button.prog { border-color: #3b82f6 !important; color: #93c5fd !important; }
        html.dark-mode .mini-btns button.net { border-color: #22c55e !important; color: #86efac !important; }

        html.dark-mode .assigned-list { border-color: #334155 !important; }
        html.dark-mode .assigned-row { border-bottom-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .tag-prog { background: #1e40af !important; color: #dbeafe !important; }
        html.dark-mode .tag-net { background: #166534 !important; color: #dcfce7 !important; }
        html.dark-mode .clear-link { color: #f87171 !important; }

        html.dark-mode .info-box { background: #1e3a8a !important; color: #bfdbfe !important; border-color: #1e40af !important; }
        html.dark-mode .warn-box { background: #78350f !important; color: #fde68a !important; border-color: #92400e !important; }
        html.dark-mode .alert-success { background: #14532d !important; color: #bbf7d0 !important; border-color: #166534 !important; }
        html.dark-mode .alert-error { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
        html.dark-mode .empty { color: #94a3b8 !important; }
        html.dark-mode .lang-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
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
            <span style="font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Lang</span>
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
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="داشبۆرد" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="ڕەشلیست" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Promote Students</h1>
        <p class="page-sub">Advance all students one stage. Stage 5 graduates.</p>

        <?php if ($success) echo "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>

        <!-- PREVIEW -->
        <div class="card">
            <h3>Promotion Preview</h3>
            <p class="desc">Total <?php echo $total; ?> students in the system.</p>
            <div class="stage-list">
                <div class="stage-row"><span>Stage 1 → Stage 2</span><span class="num"><?php echo $stages[1]; ?></span></div>
                <div class="stage-row"><span>Stage 2 → Stage 3</span><span class="num"><?php echo $stages[2]; ?></span></div>
                <div class="stage-row highlight"><span>Stage 3 → Stage 4 <span style="color:#92400e; font-size:12px;">(field required)</span></span><span class="num"><?php echo $stages[3]; ?></span></div>
                <div class="stage-row"><span>Stage 4 → Stage 5</span><span class="num"><?php echo $stages[4]; ?></span></div>
                <div class="stage-row"><span>Stage 5 → Graduated</span><span class="num"><?php echo $stages[5]; ?></span></div>
            </div>
        </div>

        <?php if ($need_count > 0): ?>
            <!-- BULK ASSIGN -->
            <div class="card">
                <h3>Step 1 — Assign Field to All</h3>
                <p class="desc">Pick one field, apply to everyone. Then override the few exceptions below.</p>
                <div class="info-box">
                    <strong><?php echo $need_count; ?></strong> Stage 3 student(s) still need a field.
                </div>
                <form method="POST">
                    <input type="hidden" name="apply_default" value="1">
                    <div class="pick-btns">
                        <label>
                            <input type="radio" name="default_field" value="Programming" checked required>
                            <span>Programming</span>
                        </label>
                        <label>
                            <input type="radio" name="default_field" value="Network" required>
                            <span>Network</span>
                        </label>
                    </div>
                    <button type="submit" class="btn-apply" onclick="return confirm('Assign this field to all remaining Stage 3 students?')">
                        Apply to All (<?php echo $need_count; ?>)
                    </button>
                </form>
            </div>

            <!-- OVERRIDE -->
            <div class="card">
                <h3>Step 2 — Fix Individual Students</h3>
                <p class="desc">Only for students who need a different field than the default.</p>
                <form method="GET" class="toolbar">
                    <input type="text" name="q" placeholder="Search name or ID..." value="<?php echo htmlspecialchars($q); ?>">
                    <button type="submit">Search</button>
                    <?php if ($q): ?><a href="promote.php">Clear</a><?php endif; ?>
                </form>
                <div class="stu-list">
                    <?php if ($need_field->num_rows === 0): ?>
                        <div class="empty">No students match your search.</div>
                    <?php else: ?>
                        <?php while ($s = $need_field->fetch_assoc()): ?>
                            <div class="stu-row">
                                <div>
                                    <span class="stu-name"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                    <span class="stu-id"><?php echo htmlspecialchars($s['student_id']); ?></span>
                                </div>
                                <div class="mini-btns">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="set_one" value="1">
                                        <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="field" value="Programming">
                                        <button type="submit" class="prog">Programming</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="set_one" value="1">
                                        <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="field" value="Network">
                                        <button type="submit" class="net">Network</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($assigned_count > 0): ?>
            <div class="card">
                <h3>Assigned (<?php echo $assigned_count; ?>)</h3>
                <p class="desc">These students already have a field. Click Clear to undo if it was a mistake.</p>
                <div class="assigned-list">
                    <?php while ($s = $already_set->fetch_assoc()): ?>
                        <div class="assigned-row">
                            <div>
                                <strong style="font-weight:600;"><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                <span class="stu-id"><?php echo htmlspecialchars($s['student_id']); ?></span>
                            </div>
                            <div>
                                <span class="tag <?php echo $s['track'] == 'Programming' ? 'tag-prog' : 'tag-net'; ?>"><?php echo htmlspecialchars($s['track']); ?></span>
                                <a href="?clear=<?php echo $s['id']; ?>" class="clear-link" onclick="return confirm('Clear this field?')">Clear</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- PROMOTE -->
        <div class="card">
            <h3>Run Promotion</h3>
            <p class="desc">This cannot be undone. Make sure everything looks right before clicking.</p>
            <div class="warn-box">
                <strong>Warning:</strong> Everyone moves up one stage. Stage 5 graduates and is deleted.
                <?php if ($need_count > 0): ?>
                    <br><strong style="color:#dc2626;"><?php echo $need_count; ?> Stage 3 students still need a field.</strong>
                <?php endif; ?>
            </div>
            <form method="POST" onsubmit="return confirm('Promote all students? This cannot be undone.')">
                <input type="hidden" name="confirm_promote" value="1">
                <button type="submit" class="btn-promote" <?php echo ($total == 0 || $need_count > 0) ? 'disabled' : ''; ?>>
                    Promote All Students (<?php echo $total; ?>)
                </button>
            </form>
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