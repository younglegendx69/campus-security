<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

// ============================================
// BACKUP — download .sql file
// ============================================
if (isset($_GET['download'])) {
    $tables = ['users', 'students', 'cars', 'blacklist', 'logs'];
    $sql = "-- ==========================================\n";
    $sql .= "-- Sardam Institute - Campus Security System\n";
    $sql .= "-- Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- ==========================================\n\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET time_zone = \"+00:00\";\n\n";

    foreach ($tables as $table) {
        $res = $conn->query("SHOW CREATE TABLE `$table`");
        if ($res) {
            $row = $res->fetch_assoc();
            $sql .= "--\n-- Table structure for `$table`\n--\n\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $row['Create Table'] . ";\n\n";
        }
        $res = $conn->query("SELECT * FROM `$table`");
        if ($res && $res->num_rows > 0) {
            $sql .= "--\n-- Data for `$table`\n--\n\n";
            while ($r = $res->fetch_assoc()) {
                $cols = array_keys($r);
                $vals = array_map(function($v) use ($conn) {
                    if ($v === null) return "NULL";
                    return "'" . $conn->real_escape_string($v) . "'";
                }, array_values($r));
                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $filename = "campus_security_backup_" . date('Y-m-d_H-i-s') . ".sql";
    $_SESSION['last_backup'] = date('Y-m-d H:i:s');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// ============================================
// RESTORE
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file']['tmp_name'];
    if (!file_exists($file) || filesize($file) === 0) {
        $error = "Please select a valid SQL file.";
    } else {
        $sql = file_get_contents($file);
        if (empty($sql)) $error = "The SQL file is empty.";
        else {
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            $ok = 0; $fail = 0;
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            foreach ($statements as $stmt) {
                if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                if ($conn->query($stmt)) $ok++;
                else $fail++;
            }
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            if ($ok > 0) $success = "Restore complete: $ok statements executed, $fail failed.";
            else $error = "No valid SQL statements found.";
        }
    }
}

// ============================================
// CLEAR ALL DATA
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_data'])) {
    if (($_POST['confirm_text'] ?? '') !== 'DELETE ALL DATA') {
        $error = "Type exactly: DELETE ALL DATA";
    } else {
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("TRUNCATE TABLE logs");
        $conn->query("TRUNCATE TABLE blacklist");
        $conn->query("TRUNCATE TABLE cars");
        $conn->query("TRUNCATE TABLE students");
        $conn->query("DELETE FROM users WHERE role != 'admin'");
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        $success = "All students, cars, blacklist, and logs deleted. Admin accounts kept.";
    }
}

// ============================================
// DB STATS  (fixed: uses DATABASE())
// ============================================
$stats = [];
$tables = ['users', 'students', 'cars', 'blacklist', 'logs'];
foreach ($tables as $t) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM `$t`");
    $count = $r ? intval($r->fetch_assoc()['c']) : 0;
    $r = $conn->query("SELECT ROUND(((data_length + index_length) / 1024), 2) AS kb FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '$t'");
    $kb = ($r && $row = $r->fetch_assoc()) ? ($row['kb'] ?: 0) : 0;
    $stats[$t] = ['rows' => $count, 'kb' => $kb];
}

$last_backup = $_SESSION['last_backup'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup & Restore - Sardam Institute</title>
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
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .card { background: #fff; padding: 28px; border-radius: 14px; border: 1px solid #e2e8f0; }
        .card h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
        .card .desc { font-size: 13.5px; color: #64748b; line-height: 1.6; margin-bottom: 18px; }
        .icon-circle { width: 40px; height: 40px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ic-blue { background: #dbeafe; } .ic-blue svg { stroke: #1e40af; }
        .ic-green { background: #dcfce7; } .ic-green svg { stroke: #166534; }
        .ic-red { background: #fee2e2; } .ic-red svg { stroke: #991b1b; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; text-decoration: none; transition: 0.15s; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-blue { background: #2563eb; color: #fff; }
        .btn-blue:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-full { width: 100%; }
        .stats-card { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; }
        .stats-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 18px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; }
        .stat-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; }
        .stat-name { font-size: 11.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 6px; font-family: monospace; }
        .stat-rows { font-size: 22px; font-weight: 700; color: #0f172a; }
        .stat-size { font-size: 11.5px; color: #94a3b8; margin-top: 4px; }
        .last-backup { background: #f1f5f9; padding: 12px 16px; border-radius: 10px; font-size: 13px; color: #475569; margin-top: 14px; display: flex; align-items: center; gap: 10px; }
        .last-backup.warn { background: #fef3c7; color: #92400e; }
        .file-input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 13.5px; background: #fff; margin-bottom: 14px; }
        .danger-zone { border: 2px solid #fecaca; }
        .danger-input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 14px; background: #fff; margin-bottom: 14px; text-transform: uppercase; }
        .tip-box { background: #e0f2fe; color: #075985; padding: 14px 18px; border-radius: 10px; font-size: 13px; line-height: 1.6; border: 1px solid #bae6fd; margin-top: 14px; }

        /* DARK MODE */
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
        html.dark-mode .stats-card { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .stats-card h3 { color: #f1f5f9 !important; }
        html.dark-mode .stat-item { background: #0f172a !important; border-color: #334155 !important; }
        html.dark-mode .stat-name { color: #94a3b8 !important; }
        html.dark-mode .stat-rows { color: #f1f5f9 !important; }
        html.dark-mode .last-backup { background: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .last-backup.warn { background: #78350f !important; color: #fde68a !important; }
        html.dark-mode .file-input { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .danger-input { background: #0f172a !important; border-color: #991b1b !important; color: #fecaca !important; }
        html.dark-mode .tip-box { background: #1e3a8a !important; color: #bfdbfe !important; border-color: #1e40af !important; }
        html.dark-mode .ic-blue { background: #1e40af !important; } html.dark-mode .ic-blue svg { stroke: #dbeafe !important; }
        html.dark-mode .ic-green { background: #166534 !important; } html.dark-mode .ic-green svg { stroke: #dcfce7 !important; }
        html.dark-mode .ic-red { background: #7f1d1d !important; } html.dark-mode .ic-red svg { stroke: #fecaca !important; }
        html.dark-mode .alert-success { background: #14532d !important; color: #bbf7d0 !important; border-color: #166534 !important; }
        html.dark-mode .alert-error { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
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
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="داشبۆرد" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="ڕەشلیست" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="backup.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Backup & Restore" data-ku="پاشەکەوت و گەڕاندنەوە" data-ar="النسخ الاحتياطي والاستعادة">Backup & Restore</h1>
        <p class="page-sub" data-en="Protect your data with one-click backup and restore" data-ku="داتاکەت بپارێزە بە پاشەکەوتکردن و گەڕاندنەوەی یەک کلیک" data-ar="احمِ بياناتك بنسخ احتياطي واستعادة بنقرة واحدة">Protect your data with one-click backup and restore</p>

        <?php if ($success) echo "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>

        <div class="stats-card">
            <h3 data-en="Database Overview" data-ku="پێداچوونەوەی داتابەیس" data-ar="نظرة عامة على قاعدة البيانات">Database Overview</h3>
            <div class="stats-grid">
                <?php foreach ($stats as $name => $s): ?>
                    <div class="stat-item">
                        <div class="stat-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="stat-rows"><?php echo $s['rows']; ?> <span style="font-size: 13px; font-weight: 500; color: #64748b;" data-en="rows" data-ku="ڕیز" data-ar="صفوف">rows</span></div>
                        <div class="stat-size"><?php echo $s['kb']; ?> KB</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>
                    <span class="icon-circle ic-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>
                    </span>
                    <span data-en="Download Backup" data-ku="داگرتنی پاشەکەوت" data-ar="تنزيل النسخة الاحتياطية">Download Backup</span>
                </h3>
                <p class="desc" data-en="Download the entire database as a .sql file. Store it somewhere safe — you can restore it anytime." data-ku="هەموو داتابەیسەکە دابگرە وەک فایلی .sql. لە شوێنێکی پارێزراو دایبنێ — دەتوانیت هەر کاتێک بیگەڕێنیتەوە." data-ar="قم بتنزيل قاعدة البيانات بالكامل كملف .sql. احفظه في مكان آمن — يمكنك استعادته في أي وقت.">Download the entire database as a .sql file. Store it somewhere safe — you can restore it anytime.</p>
                <a href="?download=1" class="btn btn-blue btn-full" onclick="return confirm('Download full database backup now?')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    <span data-en="Download .sql File" data-ku="داگرتنی فایلی .sql" data-ar="تنزيل ملف .sql">Download .sql File</span>
                </a>
                <?php if ($last_backup): ?>
                    <div class="last-backup">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <span data-en="Last backup:" data-ku="دوایین پاشەکەوت:" data-ar="آخر نسخة احتياطية:">Last backup:</span> <strong><?php echo $last_backup; ?></strong>
                    </div>
                <?php else: ?>
                    <div class="last-backup warn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span data-en="No backup has been made yet. Back up now!" data-ku="هیچ پاشەکەوتێک نەکراوە. ئێستا پاشەکەوتی بکە!" data-ar="لم يتم عمل أي نسخة احتياطية بعد. قم بالنسخ الآن!">No backup has been made yet. Back up now!</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>
                    <span class="icon-circle ic-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg>
                    </span>
                    <span data-en="Restore Database" data-ku="گەڕاندنەوەی داتابەیس" data-ar="استعادة قاعدة البيانات">Restore Database</span>
                </h3>
                <p class="desc" data-en="Upload a previously downloaded .sql backup file to restore everything." data-ku="فایلێکی .sql پاشەکەوتکراو بار بکە بۆ گەڕاندنەوەی هەموو شتێک." data-ar="ارفع ملف .sql احتياطي تم تنزيله مسبقاً لاستعادة كل شيء.">Upload a previously downloaded .sql backup file to restore everything.</p>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Restore will overwrite existing data. Continue?')">
                    <input type="file" name="sql_file" accept=".sql" required class="file-input">
                    <button type="submit" class="btn btn-primary btn-full">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                        <span data-en="Upload & Restore" data-ku="بارکردن و گەڕاندنەوە" data-ar="رفع واستعادة">Upload & Restore</span>
                    </button>
                </form>
                <div class="tip-box">
                    <strong data-en="Tip:" data-ku="ئامۆژگاری:" data-ar="نصيحة:">Tip:</strong>
                    <span data-en="Restoring will DROP tables and re-create them. Existing data will be lost unless it's in the backup." data-ku="گەڕاندنەوە خشتەکان دەسڕێتەوە و دووبارە دروستی دەکات. داتای ئێستا لەدەست دەچێت مەگەر لە پاشەکەوتەکەدا بێت." data-ar="الاستعادة ستحذف الجداول وتعيد إنشاءها. ستفقد البيانات الحالية إلا إذا كانت في النسخة الاحتياطية.">Restoring will DROP tables and re-create them. Existing data will be lost unless it's in the backup.</span>
                </div>
            </div>
        </div>

        <div class="card danger-zone">
            <h3>
                <span class="icon-circle ic-red">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </span>
                <span style="color: #dc2626;" data-en="Danger Zone" data-ku="ناوچەی مەترسی" data-ar="منطقة الخطر">Danger Zone</span>
            </h3>
            <p class="desc" data-en="Delete ALL students, cars, blacklist entries, and logs. Admin accounts are kept. Cannot be undone. Make a backup first!" data-ku="هەموو قوتابیان، ئۆتۆمبێلەکان، ڕەشلیست، و تۆمارەکان بسڕەوە. هەژماری ئەدمین دەمێنێتەوە. ناگەڕێتەوە. سەرەتا پاشەکەوت بکە!" data-ar="احذف جميع الطلاب والسيارات والقائمة السوداء والسجلات. يتم الاحتفاظ بحسابات المسؤول. لا يمكن التراجع. قم بعمل نسخة احتياطية أولاً!">Delete ALL students, cars, blacklist entries, and logs. Admin accounts are kept. Cannot be undone. Make a backup first!</p>
            <form method="POST" onsubmit="return confirm('This will DELETE ALL DATA. Are you 100% sure?')">
                <input type="text" name="confirm_text" class="danger-input" placeholder="Type: DELETE ALL DATA" required>
                <button type="submit" name="clear_data" value="1" class="btn btn-danger btn-full">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    <span data-en="Delete All Data" data-ku="سڕینەوەی هەموو داتا" data-ar="حذف جميع البيانات">Delete All Data</span>
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