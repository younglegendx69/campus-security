<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_promote'])) {
    $promoted = 0;
    $graduated = 0;
    $grad = $conn->query("SELECT id FROM students WHERE stage = 5");
    $graduated = $grad->num_rows;
    $conn->query("DELETE FROM students WHERE stage = 5");
    for ($from = 4; $from >= 1; $from--) {
        $to = $from + 1;
        $stmt = $conn->prepare("UPDATE students SET stage = ? WHERE stage = ?");
        $stmt->bind_param("ii", $to, $from);
        $stmt->execute();
        $promoted += $stmt->affected_rows;
    }
    $success = "Promotion complete: $promoted student(s) promoted, $graduated graduated (removed).";
}

$stages = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$r = $conn->query("SELECT stage, COUNT(*) AS c FROM students GROUP BY stage");
while ($row = $r->fetch_assoc()) { $stages[intval($row['stage'])] = intval($row['c']); }
$total = array_sum($stages);
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
        .lang-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 800px; }
        .card h3 { font-size: 15px; font-weight: 700; margin-bottom: 16px; }
        .warning-box { background: #fef3c7; color: #92400e; padding: 16px 20px; border-radius: 10px; font-size: 13.5px; margin-bottom: 24px; border: 1px solid #fde68a; line-height: 1.7; }
        .warning-box strong { display: block; margin-bottom: 6px; font-size: 14px; }
        .promo-flow { display: grid; gap: 12px; margin: 24px 0; }
        .flow-row { display: flex; align-items: center; gap: 14px; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .flow-arrow { color: #64748b; font-weight: 600; }
        .stage-pill { display: inline-block; padding: 5px 14px; border-radius: 8px; font-weight: 600; font-size: 13px; }
        .stage-1 { background: #dbeafe; color: #1e40af; }
        .stage-2 { background: #dcfce7; color: #166534; }
        .stage-3 { background: #fef3c7; color: #92400e; }
        .stage-4 { background: #fae8ff; color: #86198f; }
        .stage-5 { background: #fee2e2; color: #991b1b; }
        .grad-pill { background: #0f172a; color: #fff; }
        .count-badge { margin-left: auto; font-family: monospace; background: #fff; padding: 3px 10px; border-radius: 5px; font-size: 12px; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-promote { background: #dc2626; color: #fff; padding: 16px 32px; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn-promote:hover { background: #b91c1c; }
        .btn-promote:disabled { background: #94a3b8; cursor: not-allowed; }
        .summary-box { background: #0f172a; color: #fff; padding: 20px 24px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .summary-box strong { color: #60a5fa; }

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
        html.dark-mode .card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .card h3 { color: #f1f5f9 !important; }
        html.dark-mode .warning-box { background: #78350f !important; color: #fde68a !important; border-color: #92400e !important; }
        html.dark-mode .warning-box strong { color: #fde68a !important; }
        html.dark-mode .summary-box { background: #0f172a !important; color: #f1f5f9 !important; border: 1px solid #334155 !important; }
        html.dark-mode .summary-box strong { color: #60a5fa !important; }
        html.dark-mode .flow-row { background: #0f172a !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .count-badge { background: #1e293b !important; color: #cbd5e1 !important; border-color: #475569 !important; }
        html.dark-mode .flow-arrow { color: #94a3b8 !important; }
        html.dark-mode .stage-1 { background: #1e40af !important; color: #dbeafe !important; }
        html.dark-mode .stage-2 { background: #166534 !important; color: #dcfce7 !important; }
        html.dark-mode .stage-3 { background: #78350f !important; color: #fef3c7 !important; }
        html.dark-mode .stage-4 { background: #86198f !important; color: #fae8ff !important; }
        html.dark-mode .stage-5 { background: #7f1d1d !important; color: #fecaca !important; }
        html.dark-mode .grad-pill { background: #f1f5f9 !important; color: #0f172a !important; }
        html.dark-mode .alert-success { background: #14532d !important; color: #bbf7d0 !important; border-color: #166534 !important; }
        html.dark-mode .alert-error { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
        html.dark-mode .lang-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .lang-btn:hover { background: #475569 !important; }
        html.dark-mode .lang-btn.active { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; }
        html.dark-mode button[onclick*="toggleTheme"] { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .card > form > button,
        html.dark-mode .btn-promote { color: #fff !important; }
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
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</h1>
        <p class="page-sub" data-en="End-of-year promotion: Stage 1→2, 2→3, 3→4, 4→5, 5 graduates" data-ku="بەرزکردنەوەی کۆتایی ساڵ: قۆناغ 1→2، 2→3، 3→4، 4→5، 5 دەردەچێت" data-ar="ترقية نهاية العام: المرحلة 1→2، 2→3، 3→4، 4→5، 5 يتخرج">End-of-year promotion: Stage 1→2, 2→3, 3→4, 4→5, 5 graduates</p>

        <?php if ($success) echo "<div class='alert alert-success'>" . htmlspecialchars($success) . "</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>" . htmlspecialchars($error) . "</div>"; ?>

        <div class="card">
            <div class="warning-box">
                <strong data-en="⚠️ This action cannot be undone" data-ku="⚠️ ئەم کارە ناگەڕێتەوە" data-ar="⚠️ لا يمكن التراجع عن هذا الإجراء">⚠️ This action cannot be undone</strong>
                <span data-en="Every student will move up one stage. Stage 5 students will be removed permanently (graduated)." data-ku="هەموو قوتابییەک قۆناغێک بەرز دەبێتەوە. قوتابیانی قۆناغی 5 بە تەواوی دەسڕێنەوە (دەردەچن)." data-ar="سينتقل كل طالب إلى مرحلة واحدة أعلى. سيتم حذف طلاب المرحلة 5 نهائياً (تخرجوا).">Every student will move up one stage. Stage 5 students will be removed permanently (graduated).</span>
            </div>

            <div class="summary-box">
                <span data-en="Total students:" data-ku="کۆی قوتابیان:" data-ar="إجمالي الطلاب:">Total students:</span> <strong><?php echo $total; ?></strong>
            </div>

            <h3 data-en="Promotion Preview" data-ku="پێشبینینی بەرزکردنەوە" data-ar="معاينة الترقية">Promotion Preview</h3>
            <div class="promo-flow">
                <div class="flow-row">
                    <span class="stage-pill stage-1" data-en="Stage 1" data-ku="قۆناغ 1" data-ar="المرحلة 1">Stage 1</span>
                    <span class="flow-arrow">→</span>
                    <span class="stage-pill stage-2" data-en="Stage 2" data-ku="قۆناغ 2" data-ar="المرحلة 2">Stage 2</span>
                    <span class="count-badge"><?php echo $stages[1]; ?></span>
                </div>
                <div class="flow-row">
                    <span class="stage-pill stage-2" data-en="Stage 2" data-ku="قۆناغ 2" data-ar="المرحلة 2">Stage 2</span>
                    <span class="flow-arrow">→</span>
                    <span class="stage-pill stage-3" data-en="Stage 3" data-ku="قۆناغ 3" data-ar="المرحلة 3">Stage 3</span>
                    <span class="count-badge"><?php echo $stages[2]; ?></span>
                </div>
                <div class="flow-row">
                    <span class="stage-pill stage-3" data-en="Stage 3" data-ku="قۆناغ 3" data-ar="المرحلة 3">Stage 3</span>
                    <span class="flow-arrow">→</span>
                    <span class="stage-pill stage-4" data-en="Stage 4" data-ku="قۆناغ 4" data-ar="المرحلة 4">Stage 4</span>
                    <span class="count-badge"><?php echo $stages[3]; ?></span>
                </div>
                <div class="flow-row">
                    <span class="stage-pill stage-4" data-en="Stage 4" data-ku="قۆناغ 4" data-ar="المرحلة 4">Stage 4</span>
                    <span class="flow-arrow">→</span>
                    <span class="stage-pill stage-5" data-en="Stage 5" data-ku="قۆناغ 5" data-ar="المرحلة 5">Stage 5</span>
                    <span class="count-badge"><?php echo $stages[4]; ?></span>
                </div>
                <div class="flow-row">
                    <span class="stage-pill stage-5" data-en="Stage 5" data-ku="قۆناغ 5" data-ar="المرحلة 5">Stage 5</span>
                    <span class="flow-arrow">→</span>
                    <span class="stage-pill grad-pill" data-en="Graduated" data-ku="دەرچوو" data-ar="تخرج">Graduated</span>
                    <span class="count-badge"><?php echo $stages[5]; ?></span>
                </div>
            </div>

            <form method="POST" onsubmit="return confirm('Promote all students? This cannot be undone.')">
                <input type="hidden" name="confirm_promote" value="1">
                <button type="submit" class="btn-promote" <?php echo $total == 0 ? 'disabled' : ''; ?> data-en="Promote All Students" data-ku="بەرزکردنەوەی هەموو قوتابیان" data-ar="ترقية جميع الطلاب">Promote All Students</button>
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