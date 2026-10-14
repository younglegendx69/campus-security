<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

function countByStage($conn, $stage) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE stage = ?");
    $stmt->bind_param("i", $stage);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['c'];
}

$counts = [];
for ($i = 1; $i <= 5; $i++) $counts[$i] = countByStage($conn, $i);
$total = array_sum($counts);

$stage3_students = [];
if ($counts[3] > 0) {
    $res = $conn->query("SELECT id, full_name, student_id, class FROM students WHERE stage = 3 ORDER BY class ASC, full_name ASC");
    while ($row = $res->fetch_assoc()) $stage3_students[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_promote'])) {
    if ($total == 0) {
        $error = "No students to promote.";
    } else {
        $fields = $_POST['field'] ?? [];
        $missing = 0;
        foreach ($stage3_students as $s) {
            $f = $fields[$s['id']] ?? '';
            if (!in_array($f, ['Programming', 'Network'])) $missing++;
        }

        if ($missing > 0) {
            $error = "Please choose a Field for every Stage 3 student. Missing: $missing";
        } else {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM students WHERE stage = 5");
                $conn->query("UPDATE students SET stage = 5 WHERE stage = 4");
                $upd = $conn->prepare("UPDATE students SET stage = 4, track = ? WHERE id = ?");
                foreach ($stage3_students as $s) {
                    $field = $fields[$s['id']];
                    $upd->bind_param("si", $field, $s['id']);
                    $upd->execute();
                }
                $conn->query("UPDATE students SET stage = 3, track = NULL WHERE stage = 2");
                $conn->query("UPDATE students SET stage = 2, track = NULL WHERE stage = 1");
                $conn->commit();
                $success = "Promotion complete! Every student moved up one stage. Stage 5 graduates were deleted.";
                for ($i = 1; $i <= 5; $i++) $counts[$i] = countByStage($conn, $i);
                $total = array_sum($counts);
                $stage3_students = [];
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Promotion failed: " . $e->getMessage();
            }
        }
    }
}
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
        .lang-btn:hover { background: #e2e8f0; color: #0f172a; }
        .lang-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }

        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }

        .warning-box { background: #fef3c7; color: #92400e; padding: 18px 22px; border-radius: 10px; font-size: 14px; margin-bottom: 24px; border: 1px solid #fde68a; line-height: 1.6; }
        .warning-box strong { color: #78350f; }

        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 28px; max-width: 1000px; margin-bottom: 20px; }
        .card h3 { font-size: 15px; font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

        .promo-list { list-style: none; padding: 0; }
        .promo-list li { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; gap: 12px; flex-wrap: wrap; }
        .promo-list li:last-child { border-bottom: none; }
        .promo-list .label { font-weight: 600; }
        .promo-from { color: #64748b; }
        .promo-to { color: #2563eb; font-weight: 600; }
        .promo-delete { color: #dc2626; font-weight: 600; }

        .total-row { display: flex; justify-content: space-between; font-size: 15px; padding-top: 16px; margin-top: 8px; border-top: 2px solid #f1f5f9; font-weight: 700; }

        .field-list { margin-top: 4px; }
        .field-item { display: grid; grid-template-columns: 50px 1fr 110px 90px 200px; gap: 12px; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
        .field-item:last-child { border-bottom: none; }
        .field-item .num { color: #94a3b8; font-size: 13px; font-weight: 600; }
        .field-item .name { font-weight: 600; color: #0f172a; }
        .field-item .sid { font-family: monospace; background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 600; text-align: center; display: block; }
        .field-item .cl { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-align: center; }
        .field-item select { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 13.5px; background: #fff; color: #0f172a; }
        .field-item select:focus { outline: none; border-color: #0f172a; }

        .helper-bar { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .helper-bar button { padding: 8px 16px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12.5px; font-weight: 500; cursor: pointer; font-family: inherit; color: #475569; }
        .helper-bar button:hover { background: #e2e8f0; color: #0f172a; }

        .btn { background: #16a34a; color: #fff; padding: 15px 32px; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; max-width: 1000px; width: 100%; font-family: inherit; }
        .btn:hover { background: #15803d; }

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }
        .empty-success { text-align: center; padding: 60px 20px; color: #166534; font-size: 15px; line-height: 1.8; }

        /* ============================================
           DARK MODE
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
        html.dark-mode .card h3 { color: #f1f5f9 !important; border-bottom-color: #334155 !important; }
        html.dark-mode .warning-box { background: #78350f !important; color: #fde68a !important; border-color: #92400e !important; }
        html.dark-mode .warning-box strong { color: #fcd34d !important; }
        html.dark-mode .promo-list li { border-bottom-color: #334155 !important; }
        html.dark-mode .promo-from { color: #94a3b8 !important; }
        html.dark-mode .total-row { border-top-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .field-item { border-bottom-color: #334155 !important; }
        html.dark-mode .field-item .num { color: #94a3b8 !important; }
        html.dark-mode .field-item .name { color: #e2e8f0 !important; }
        html.dark-mode .field-item .sid { background: #334155 !important; color: #ffffff !important; }
        html.dark-mode .field-item .cl { background: #78350f !important; color: #fde68a !important; }
        html.dark-mode .field-item select { background: #0f172a !important; border-color: #475569 !important; color: #ffffff !important; }
        html.dark-mode .helper-bar button { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .helper-bar button:hover { background: #475569 !important; color: #ffffff !important; }
        html.dark-mode .empty-success { color: #86efac !important; }
        html.dark-mode .empty { color: #94a3b8 !important; }
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
        <h1 class="page-title" data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</h1>
        <p class="page-sub" data-en="Advance every student exactly one stage. Stage 5 students graduate (deleted)." data-ku="هەموو قوتابییەک بە تەواوی یەک قۆناغ بەرز بکەرەوە. قوتابیانی قۆناغی 5 دەردەچن (دەسڕێنەوە)." data-ar="تقدم كل طالب مرحلة واحدة بالضبط. طلاب المرحلة 5 يتخرجون (يُحذفون).">Advance every student exactly one stage. Stage 5 students graduate (deleted).</p>

        <?php if (!$success): ?>
        <div class="warning-box">
            <strong data-en="⚠️ Warning:" data-ku="⚠️ ئاگاداری:" data-ar="⚠️ تحذير:">⚠️ Warning:</strong>
            <span data-en=" This cannot be undone. Every student moves up exactly one stage:" data-ku=" ئەمە ناگەڕێتەوە. هەموو قوتابییەک بە تەواوی یەک قۆناغ بەرز دەبێتەوە:" data-ar=" هذا لا يمكن التراجع عنه. كل طالب ينتقل مرحلة واحدة بالضبط:"> This cannot be undone. Every student moves up exactly one stage:</span>
            <br>• <span data-en="Stage 1 → 2" data-ku="قۆناغ 1 → 2" data-ar="المرحلة 1 → 2">Stage 1 → 2</span>
            <br>• <span data-en="Stage 2 → 3" data-ku="قۆناغ 2 → 3" data-ar="المرحلة 2 → 3">Stage 2 → 3</span>
            <br>• <span data-en="Stage 3 → 4 (assign Field below)" data-ku="قۆناغ 3 → 4 (لە خوارەوە بەش دیاری بکە)" data-ar="المرحلة 3 → 4 (عيّن التخصص أدناه)">Stage 3 → 4 (assign Field below)</span>
            <br>• <span data-en="Stage 4 → 5 (keep Field)" data-ku="قۆناغ 4 → 5 (بەش دەپارێزرێت)" data-ar="المرحلة 4 → 5 (يُحفظ التخصص)">Stage 4 → 5 (keep Field)</span>
            <br>• <span data-en="Stage 5 → Deleted (Graduated)" data-ku="قۆناغ 5 → دەسڕێتەوە (دەردەچێت)" data-ar="المرحلة 5 → يُحذف (يتخرج)">Stage 5 → Deleted (Graduated)</span>
            <br><br>
            <strong data-en="Backup first!" data-ku="سەرەتا پاشەکەوت بکە!" data-ar="قم بالنسخ الاحتياطي أولاً!">Backup first!</strong>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div class="card" style="text-align: center;">
                <div class="empty-success">
                    <strong data-en="✅ Promotion Complete!" data-ku="✅ بەرزکردنەوە تەواو بوو!" data-ar="✅ اكتملت الترقية!">✅ Promotion Complete!</strong><br><br>
                    <span data-en="Every student moved up exactly ONE stage. Stage 5 graduates were deleted." data-ku="هەموو قوتابییەک بە تەواوی یەک قۆناغ بەرز بوو. قوتابیانی قۆناغی 5 دەسڕێنەوە." data-ar="انتقل كل طالب مرحلة واحدة بالضبط. تم حذف طلاب المرحلة 5.">Every student moved up exactly ONE stage. Stage 5 graduates were deleted.</span><br><br>
                    <a href="promote.php" style="color: #2563eb;" data-en="Promote again" data-ku="دووبارە بەرزکردنەوە" data-ar="ترقية مرة أخرى">Promote again</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>

        <?php if (!$success && $total > 0): ?>
        <div class="card">
            <h3 data-en="📊 Preview — What Will Happen" data-ku="📊 پێشبینین — چی ڕوودەدات" data-ar="📊 معاينة — ما سيحدث">📊 Preview — What Will Happen</h3>
            <ul class="promo-list">
                <li><span class="label promo-from">Stage 1</span><span><strong><?php echo $counts[1]; ?></strong></span><span class="promo-to">→ Stage 2</span></li>
                <li><span class="label promo-from">Stage 2</span><span><strong><?php echo $counts[2]; ?></strong></span><span class="promo-to">→ Stage 3</span></li>
                <li><span class="label promo-from">Stage 3</span><span><strong><?php echo $counts[3]; ?></strong></span><span class="promo-to">→ Stage 4</span></li>
                <li><span class="label promo-from">Stage 4</span><span><strong><?php echo $counts[4]; ?></strong></span><span class="promo-to">→ Stage 5</span></li>
                <li><span class="label promo-from">Stage 5</span><span><strong><?php echo $counts[5]; ?></strong></span><span class="promo-delete">→ Graduate</span></li>
            </ul>
            <div class="total-row"><span data-en="Total students affected:" data-ku="کۆی قوتابیانی کارتێکراو:" data-ar="إجمالي الطلاب المتأثرين:">Total students affected:</span><span><?php echo $total; ?></span></div>
        </div>
        <?php endif; ?>

        <?php if (!$success && count($stage3_students) > 0): ?>
        <form method="POST" onsubmit="return confirmPromote()">
            <div class="card">
                <h3 data-en="👥 Assign a Field to Each Stage 3 Student" data-ku="👥 دیاریکردنی بەش بۆ هەر قوتابییەکی قۆناغی 3" data-ar="👥 تعيين تخصص لكل طالب في المرحلة 3">👥 Assign a Field to Each Stage 3 Student</h3>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;" data-en="Every Stage 3 student moves to Stage 4 and must have a Field." data-ku="هەموو قوتابییەکی قۆناغی 3 دەچێتە قۆناغی 4 و دەبێت بەشێکی هەبێت." data-ar="كل طالب في المرحلة 3 ينتقل إلى المرحلة 4 ويجب أن يكون له تخصص.">Every Stage 3 student moves to Stage 4 and must have a Field.</p>

                <div class="helper-bar">
                    <button type="button" onclick="setAll('Programming')" data-en="Set All → Programming" data-ku="هەموو → بەرنامەسازی" data-ar="تعيين الكل → البرمجة">Set All → Programming</button>
                    <button type="button" onclick="setAll('Network')" data-en="Set All → Network" data-ku="هەموو → تۆڕ" data-ar="تعيين الكل → الشبكات">Set All → Network</button>
                    <button type="button" onclick="setAll('')" data-en="Clear All" data-ku="سڕینەوەی هەموو" data-ar="مسح الكل">Clear All</button>
                </div>

                <div class="field-list">
                    <?php foreach ($stage3_students as $i => $s): ?>
                        <div class="field-item">
                            <div class="num">#<?php echo $i + 1; ?></div>
                            <div class="name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                            <div class="sid"><?php echo htmlspecialchars($s['student_id']); ?></div>
                            <div class="cl">Class <?php echo htmlspecialchars($s['class']); ?></div>
                            <select name="field[<?php echo $s['id']; ?>]" class="field-select" required>
                                <option value="" data-en="-- Choose Field --" data-ku="-- بەش هەڵبژێرە --" data-ar="-- اختر التخصص --">-- Choose Field --</option>
                                <option value="Programming" data-en="Programming" data-ku="بەرنامەسازی" data-ar="البرمجة">Programming</option>
                                <option value="Network" data-en="Network" data-ku="تۆڕ" data-ar="الشبكات">Network</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="confirm_promote" class="btn" data-en="✅ Confirm Promotion — Advance All Students" data-ku="✅ دڵنیاکردنەوەی بەرزکردنەوە — هەموو قوتابیان بەرز بکەرەوە" data-ar="✅ تأكيد الترقية — ترقية جميع الطلاب">✅ Confirm Promotion — Advance All Students</button>
        </form>
        <?php elseif (!$success && $total > 0): ?>
            <form method="POST" onsubmit="return confirmPromote()">
                <button type="submit" name="confirm_promote" class="btn" data-en="✅ Confirm Promotion — Advance All Students" data-ku="✅ دڵنیاکردنەوەی بەرزکردنەوە — هەموو قوتابیان بەرز بکەرەوە" data-ar="✅ تأكيد الترقية — ترقية جميع الطلاب">✅ Confirm Promotion — Advance All Students</button>
            </form>
        <?php elseif (!$success): ?>
            <div class="card">
                <div class="empty" data-en="No students in the system to promote." data-ku="هیچ قوتابییەک لە سیستەمدا نییە بۆ بەرزکردنەوە." data-ar="لا يوجد طلاب في النظام لترقيتهم.">No students in the system to promote.</div>
            </div>
        <?php endif; ?>
    </main>

    <script src="language.js?v=99"></script>
    <script>
    function setAll(value) {
        document.querySelectorAll('.field-select').forEach(sel => sel.value = value);
    }
    function confirmPromote() {
        const selects = document.querySelectorAll('.field-select');
        let missing = 0;
        selects.forEach(s => { if (!s.value) missing++; });
        if (missing > 0) {
            alert('Please choose a Field for every Stage 3 student. Missing: ' + missing);
            return false;
        }
        return confirm('Are you SURE?\n\nEvery student moves up ONE stage.\nStage 5 students will be DELETED (graduated).\nThis cannot be undone.');
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>