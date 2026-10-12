<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";
$preview = [];
$csv_processed = false;
$csv_ids = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ids']) && !isset($_POST['from_csv'])) {
    $ids = $_POST['ids'];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = intval($id);
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $deleted++;
    }
    $success = "$deleted student(s) deleted.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['from_csv']) && isset($_POST['csv_ids'])) {
    $ids = $_POST['csv_ids'];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = intval($id);
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $deleted++;
    }
    $success = "$deleted student(s) deleted.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file']) && $_FILES['csv_file']['size'] > 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle === false) $error = "Cannot open file.";
    else {
        $csv_processed = true;
        $header = fgetcsv($handle);
        $mode = 'id';
        if ($header && (strtolower(trim($header[0])) == 'full_name' || strtolower(trim($header[0])) == 'name')) $mode = 'name';
        else { rewind($file); $handle = fopen($file, "r"); }
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(trim($row[0]))) continue;
            $value = trim($row[0]);
            if ($mode === 'id') $stmt = $conn->prepare("SELECT id, student_id, full_name, stage, class FROM students WHERE student_id = ?");
            else $stmt = $conn->prepare("SELECT id, student_id, full_name, stage, class FROM students WHERE full_name = ?");
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r->num_rows > 0) { while ($s = $r->fetch_assoc()) { $preview[] = $s; $csv_ids[] = $s['id']; } }
            else $preview[] = ['not_found' => $value];
        }
        fclose($handle);
        if (empty($preview)) { $error = "No valid data."; $csv_processed = false; }
    }
}

$search = "";
$where = "";
if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $where = "WHERE s.full_name LIKE '%$search%' OR s.student_id LIKE '%$search%'";
}

$students = $conn->query("
    SELECT s.*, (SELECT COUNT(*) FROM cars WHERE student_id = s.id) AS car_count
    FROM students s $where ORDER BY s.full_name ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Delete - Sardam Institute</title>
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
        .warning-box { background: #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 20px; border: 1px solid #fde68a; }
        .tabs { display: flex; gap: 4px; margin-bottom: 20px; background: #f1f5f9; padding: 4px; border-radius: 10px; width: fit-content; }
        .tab { padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #64748b; cursor: pointer; text-decoration: none; }
        .tab.active { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .csv-card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 800px; margin-bottom: 20px; }
        .csv-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 14px; }
        .instructions { background: #f1f5f9; padding: 16px 20px; border-radius: 8px; font-size: 13px; color: #475569; line-height: 1.7; margin-bottom: 20px; }
        .instructions code { background: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #dc2626; display: block; margin: 8px 0; overflow-x: auto; }
        .form-group { margin-bottom: 18px; }
        input[type=file] { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; background: #fff; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-download { display: inline-block; background: #2563eb; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 12.5px; font-weight: 500; cursor: pointer; margin-top: 8px; }
        .preview-card { background: #fff; border: 2px solid #dc2626; border-radius: 12px; padding: 24px; max-width: 800px; margin-bottom: 20px; }
        .preview-card h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; color: #dc2626; }
        .preview-list { max-height: 300px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 8px; margin-bottom: 16px; }
        .preview-row { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center; }
        .preview-row.matched { background: #fef2f2; }
        .preview-row.not-found { background: #f8fafc; color: #94a3b8; }
        .preview-summary { font-size: 14px; margin-bottom: 16px; color: #334155; }
        .btn-delete-all { background: #dc2626; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .btn-cancel { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .toolbar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
        .toolbar form.search-form { display: flex; flex: 1; gap: 12px; }
        .toolbar input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-family: inherit; }
        .toolbar button { background: #0f172a; color: #fff; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-delete-selected { background: #dc2626; color: #fff; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-delete-selected:disabled { background: #94a3b8; cursor: not-allowed; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #fef2f2; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; vertical-align: middle; margin-right: 10px; }
        .student-id { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .checkbox-cell { width: 50px; text-align: center; }
        input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #dc2626; }
        .empty { text-align: center; padding: 60px; color: #64748b; }

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
        html.dark-mode .warning-box { background: #78350f !important; color: #fde68a !important; border-color: #92400e !important; }
        html.dark-mode .tabs { background: #1e293b !important; }
        html.dark-mode .tab { color: #94a3b8 !important; }
        html.dark-mode .tab.active { background: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .csv-card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .csv-card h3 { color: #f1f5f9 !important; }
        html.dark-mode .instructions { background: #0f172a !important; color: #cbd5e1 !important; }
        html.dark-mode .instructions code { background: #1e293b !important; color: #fca5a5 !important; }
        html.dark-mode input[type=file] { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .btn { background: #3b82f6 !important; }
        html.dark-mode .toolbar input { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .toolbar button { background: #3b82f6 !important; }
        html.dark-mode .table-card { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode th { background: #0f172a !important; color: #94a3b8 !important; }
        html.dark-mode td { color: #f1f5f9 !important; border-bottom-color: #334155 !important; }
        html.dark-mode tr:hover td { background: #334155 !important; }
        html.dark-mode .student-id { background: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .avatar-sm { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .preview-card { background: #1e293b !important; border-color: #dc2626 !important; color: #f1f5f9 !important; }
        html.dark-mode .preview-list { border-color: #334155 !important; }
        html.dark-mode .preview-row { border-bottom-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .preview-row.matched { background: #7f1d1d !important; }
        html.dark-mode .preview-row.not-found { background: #0f172a !important; color: #94a3b8 !important; }
        html.dark-mode .preview-summary { color: #cbd5e1 !important; }
        html.dark-mode .btn-cancel { background: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .empty { color: #94a3b8 !important; }
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
            <a href="bulk_delete.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="ڕەشلیست" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Bulk Delete Students" data-ku="سڕینەوەی بەکۆمەڵی قوتابیان" data-ar="حذف الطلاب جماعياً">Bulk Delete Students</h1>
        <p class="page-sub" data-en="Delete via CSV or manual selection" data-ku="سڕینەوە بە CSV یان هەڵبژاردنی دەستی" data-ar="حذف عبر CSV أو اختيار يدوي">Delete via CSV or manual selection</p>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="warning-box" data-en="Deleting a student also deletes their cars. Cannot be undone." data-ku="سڕینەوەی قوتابی ئۆتۆمبێلەکانی دەسڕێتەوە. ناگەڕێتەوە." data-ar="حذف الطالب يحذف سياراته. لا يمكن التراجع.">Deleting a student also deletes their cars. Cannot be undone.</div>

        <div class="tabs">
            <a href="?tab=csv" class="tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'csv') ? 'active' : ''; ?>">CSV Upload</a>
            <a href="?tab=manual" class="tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'manual') ? 'active' : ''; ?>">Manual Selection</a>
        </div>

        <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'csv'): ?>
            <?php if ($csv_processed && !empty($preview)): ?>
                <div class="preview-card">
                    <h3 data-en="Confirm Deletion" data-ku="دڵنیاکردنەوەی سڕینەوە" data-ar="تأكيد الحذف">Confirm Deletion</h3>
                    <div class="preview-summary"><span data-en="Found" data-ku="دۆزرایەوە" data-ar="تم العثور على">Found</span> <strong><?php echo count($csv_ids); ?></strong> <span data-en="matching students" data-ku="قوتابی هاوتا" data-ar="طلاب مطابقون">matching students</span></div>
                    <div class="preview-list">
                        <?php foreach ($preview as $p): ?>
                            <?php if (isset($p['not_found'])): ?>
                                <div class="preview-row not-found"><span><?php echo htmlspecialchars($p['not_found']); ?></span><span>Not found</span></div>
                            <?php else: ?>
                                <div class="preview-row matched"><span><strong><?php echo htmlspecialchars($p['full_name']); ?></strong> — <?php echo htmlspecialchars($p['student_id']); ?></span><span>Stage <?php echo intval($p['stage']); ?></span></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="from_csv" value="1">
                        <?php foreach ($csv_ids as $id): ?><input type="hidden" name="csv_ids[]" value="<?php echo $id; ?>"><?php endforeach; ?>
                        <button type="submit" class="btn-delete-all">Delete All (<?php echo count($csv_ids); ?>)</button>
                        <a href="bulk_delete.php?tab=csv" class="btn-cancel">Cancel</a>
                    </form>
                </div>
            <?php else: ?>
                <div class="csv-card">
                    <h3 data-en="Upload CSV File" data-ku="بارکردنی فایلی CSV" data-ar="رفع ملف CSV">Upload CSV File</h3>
                    <div class="instructions">
                        <strong data-en="By Student ID:" data-ku="بە ژمارەی قوتابی:" data-ar="برقم الطالب:">By Student ID:</strong>
                        <code>student_id<br>STU001<br>STU002</code>
                        <strong data-en="Or by Full Name:" data-ku="یان بە ناوی تەواو:" data-ar="أو بالاسم الكامل:">Or by Full Name:</strong>
                        <code>full_name<br>Ahmed Ali<br>Sara Ahmed</code>
                        <a class="btn-download" onclick="downloadTemplate()" data-en="Download Template" data-ku="داگرتنی نموونە" data-ar="تحميل النموذج">Download Template</a>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label data-en="Select CSV File" data-ku="فایلی CSV هەڵبژێرە" data-ar="اختر ملف CSV">Select CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn" data-en="Upload & Preview" data-ku="بارکردن و پێشبینین" data-ar="رفع ومعاينة">Upload & Preview</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="toolbar">
                <form method="GET" class="search-form">
                    <input type="hidden" name="tab" value="manual">
                    <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" data-en="Search" data-ku="گەڕان" data-ar="بحث">Search</button>
                </form>
                <form method="POST" id="deleteForm" onsubmit="return confirmDelete()">
                    <div id="hidden-ids"></div>
                    <button type="submit" class="btn-delete-selected" id="deleteBtn" disabled>Delete Selected (0)</button>
                </form>
            </div>

            <?php if ($students->num_rows === 0): ?>
                <div class="empty" data-en="No students found." data-ku="هیچ قوتابییەک نەدۆزرایەوە." data-ar="لا يوجد طلاب.">No students found.</div>
            <?php else: ?>
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell"><input type="checkbox" id="selectAll"></th>
                                <th data-en="Student" data-ku="قوتابی" data-ar="الطالب">Student</th>
                                <th data-en="Student ID" data-ku="ژمارەی قوتابی" data-ar="رقم الطالب">Student ID</th>
                                <th data-en="Stage" data-ku="قۆناغ" data-ar="المرحلة">Stage</th>
                                <th data-en="Class" data-ku="پۆل" data-ar="الصف">Class</th>
                                <th data-en="Cars" data-ku="ئۆتۆمبێل" data-ar="سيارات">Cars</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <tr>
                                    <td class="checkbox-cell"><input type="checkbox" class="student-checkbox" value="<?php echo $s['id']; ?>"></td>
                                    <td><span class="avatar-sm"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></span><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                                    <td><span class="student-id"><?php echo htmlspecialchars($s['student_id']); ?></span></td>
                                    <td>Stage <?php echo intval($s['stage']); ?></td>
                                    <td>Class <?php echo htmlspecialchars($s['class']); ?></td>
                                    <td><?php echo $s['car_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="language.js?v=99"></script>
    <script>
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const deleteBtn = document.getElementById('deleteBtn');

    function updateButton() {
        if (!deleteBtn) return;
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        deleteBtn.textContent = 'Delete Selected (' + checked + ')';
        deleteBtn.disabled = checked === 0;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateButton();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateButton));

    function confirmDelete() {
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        if (checked === 0) return false;
        if (!confirm('Delete ' + checked + ' student(s)?')) return false;
        const hidden = document.getElementById('hidden-ids');
        hidden.innerHTML = '';
        document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            hidden.appendChild(input);
        });
        return true;
    }

    function downloadTemplate() {
        var csv = "student_id\nSTU001\nSTU002\nSTU003\n";
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'delete_students_template.csv';
        a.click();
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>