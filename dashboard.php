<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';
$is_admin = ($_SESSION['role'] == 'admin');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Sardam Institute</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css?v=3">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { background: #f8fafc; color: #0f172a; font-family: 'Inter', sans-serif; font-size: 14px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; z-index: 50; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; color: #0f172a; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .lang-bar { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .lang-bar .lbl { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-right: 4px; }
        .lang-btn { background: #f1f5f9; border: 1px solid #e2e8f0; height: 28px; padding: 0 10px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 11.5px; color: #475569; font-family: inherit; white-space: nowrap; transition: 0.15s; }
        .lang-btn:hover { background: #e2e8f0; color: #0f172a; }
        .lang-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
        .theme-btn { background: #f1f5f9; border: 1px solid #e2e8f0; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0; color: #475569; }
        .sidebar nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; transition: 0.15s; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; flex-shrink: 0; }
        .main { margin-left: 250px; min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .topbar h1 { font-size: 18px; font-weight: 600; color: #0f172a; }
        .topbar .user { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; }
        .user-info .name { font-size: 13px; font-weight: 600; line-height: 1.2; color: #0f172a; }
        .user-info .role { font-size: 11.5px; color: #64748b; text-transform: capitalize; }
        .logout-btn { padding: 8px 14px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .logout-btn:hover { background: #f1f5f9; color: #dc2626; border-color: #fecaca; }
        .content { padding: 32px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; color: #0f172a; }
        .stat-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .stat-label { font-size: 12.5px; color: #64748b; font-weight: 500; }
        .stat-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .stat-value { font-size: 26px; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .section-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; color: #0f172a; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .card { background: #fff; padding: 22px; border-radius: 12px; border: 1px solid #e2e8f0; text-decoration: none; color: #0f172a; transition: 0.15s; display: block; }
        .card:hover { border-color: #94a3b8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); }
        .card .icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; background: #f1f5f9; }
        .card h3 { font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #0f172a; }
        .card p { font-size: 12.5px; color: #64748b; line-height: 1.5; }
        svg { stroke-linecap: round; stroke-linejoin: round; }
        .ic-blue { stroke: #2563eb; } .ic-green { stroke: #16a34a; }
        .ic-purple { stroke: #7c3aed; } .ic-orange { stroke: #ea580c; }
        .ic-red { stroke: #dc2626; } .ic-cyan { stroke: #0891b2; } .ic-yellow { stroke: #ca8a04; }

        /* DARK MODE — all local so nothing can break it */
        html.dark-mode, html.dark-mode body { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .sidebar { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .sidebar-header { border-color: #334155 !important; }
        html.dark-mode .sidebar-header .name { color: #f1f5f9 !important; }
        html.dark-mode .sidebar-header .name span { color: #94a3b8 !important; }
        html.dark-mode .lang-bar { border-color: #334155 !important; }
        html.dark-mode .lang-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .lang-btn:hover { background: #475569 !important; }
        html.dark-mode .lang-btn.active { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; }
        html.dark-mode .theme-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .sidebar nav a { color: #94a3b8 !important; }
        html.dark-mode .sidebar nav a:hover { background: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .sidebar nav a.active { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .nav-label { color: #64748b !important; }
        html.dark-mode .topbar { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .topbar h1 { color: #f1f5f9 !important; }
        html.dark-mode .user-info .name { color: #f1f5f9 !important; }
        html.dark-mode .user-info .role { color: #94a3b8 !important; }
        html.dark-mode .logout-btn { background: #334155 !important; color: #e2e8f0 !important; border-color: #475569 !important; }
        html.dark-mode .stat, html.dark-mode .card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .stat-value { color: #f1f5f9 !important; }
        html.dark-mode .stat-label, html.dark-mode .stat-sub { color: #94a3b8 !important; }
        html.dark-mode .stat-icon, html.dark-mode .card .icon-box { background: #0f172a !important; }
        html.dark-mode .section-title { color: #f1f5f9 !important; }
        html.dark-mode .card h3 { color: #f1f5f9 !important; }
        html.dark-mode .card p { color: #94a3b8 !important; }

        @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } .grid { grid-template-columns: repeat(2, 1fr); } }
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

        <div class="lang-bar">
            <span class="lbl" data-en="Lang" data-ku="زمان" data-ar="لغة">Lang</span>
            <button onclick="setLanguage('en')" class="lang-btn" data-lang-btn="en">English</button>
            <button onclick="setLanguage('ku')" class="lang-btn" data-lang-btn="ku">کوردی</button>
            <button onclick="setLanguage('ar')" class="lang-btn" data-lang-btn="ar">عربي</button>
            <button onclick="toggleTheme()" class="theme-btn" title="Theme">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>

        <nav>
            <div class="nav-label" data-en="Main" data-ku="سەرەکی" data-ar="الرئيسية">Main</div>
            <a href="dashboard.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</span></a>
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
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1 data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</h1>
            <div class="user">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
                <a href="logout.php" class="logout-btn" data-en="Sign out" data-ku="چوونەدەرەوە" data-ar="خروج">Sign out</a>
            </div>
        </div>

        <div class="content">
            <div class="stats">
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label" data-en="Total Students" data-ku="کۆی قوتابیان" data-ar="إجمالي الطلاب">Total Students</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-blue" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM students"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub" data-en="Registered" data-ku="تۆمارکراو" data-ar="مسجل">Registered</div>
                </div>
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label" data-en="Registered Cars" data-ku="ئۆتۆمبێلە تۆمارکراوەکان" data-ar="السيارات المسجلة">Registered Cars</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-green" stroke-width="2"><path d="M5 17h14M5 17v-6l2-5h10l2 5v6"/><circle cx="7.5" cy="14.5" r="1.5"/><circle cx="16.5" cy="14.5" r="1.5"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM cars"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub" data-en="Active vehicles" data-ku="ئۆتۆمبێلە چالاکەکان" data-ar="مركبات نشطة">Active vehicles</div>
                </div>
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label" data-en="Blacklisted" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklisted</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-red" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM blacklist"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub" data-en="Banned vehicles" data-ku="ئۆتۆمبێلە قەدەغەکراوەکان" data-ar="مركبات محظورة">Banned vehicles</div>
                </div>
            </div>

            <div class="section-title" data-en="Quick Actions" data-ku="کارە خێراکان" data-ar="إجراءات سريعة">Quick Actions</div>
            <div class="grid">
                <a href="add_student.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-blue" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></div>
                    <h3 data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</h3>
                    <p data-en="Register a new student" data-ku="تۆمارکردنی قوتابی نوێ" data-ar="تسجيل طالب جديد">Register a new student</p>
                </a>
                <a href="bulk_import.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-cyan" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg></div>
                    <h3 data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</h3>
                    <p data-en="Add many from CSV" data-ku="زیادکردنی زۆر لە CSV" data-ar="إضافة الكثير من CSV">Add many from CSV</p>
                </a>
                <a href="search_plate.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-purple" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
                    <h3 data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</h3>
                    <p data-en="Find by plate or name" data-ku="گەڕان بە پلاک یان ناو" data-ar="البحث باللوحة أو الاسم">Find by plate or name</p>
                </a>
                <a href="view_students.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-cyan" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div>
                    <h3 data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</h3>
                    <p data-en="Browse student list" data-ku="بینینی لیستی قوتابیان" data-ar="تصفح قائمة الطلاب">Browse student list</p>
                </a>
                <a href="promote.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-yellow" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg></div>
                    <h3 data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</h3>
                    <p data-en="Advance to next stage" data-ku="بەرزکردنەوە بۆ قۆناغی داهاتوو" data-ar="التقدم إلى المرحلة التالية">Advance to next stage</p>
                </a>
                <a href="blacklist.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-red" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                    <h3 data-en="Blacklist" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklist</h3>
                    <p data-en="Manage banned vehicles" data-ku="بەڕێوەبردنی ئۆتۆمبێلە قەدەغەکراوەکان" data-ar="إدارة المركبات المحظورة">Manage banned vehicles</p>
                </a>
            </div>
        </div>
    </main>

    <script src="language.js?v=3"></script>
    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    // make sure active lang btn highlights
    document.addEventListener('DOMContentLoaded', function() {
        var lang = localStorage.getItem('lang') || 'en';
        document.querySelectorAll('[data-lang-btn]').forEach(function(b) {
            if (b.getAttribute('data-lang-btn') === lang) b.classList.add('active');
        });
    });
    // patch setLanguage so it toggles active too
    var _origSetLang = window.setLanguage;
    window.setLanguage = function(l) {
        if (typeof _origSetLang === 'function') _origSetLang(l);
        document.querySelectorAll('[data-lang-btn]').forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-lang-btn') === l);
        });
    };
    </script>
</body>
</html>